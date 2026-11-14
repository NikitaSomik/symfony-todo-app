<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\ValueObject\Email;
use App\Fixtures\Auth\RefreshTokenFactory;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\TestJwtKeys;
use Doctrine\ORM\EntityManagerInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HmacSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RsaSha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;

final class AccessTokenTest extends ApiTestCase
{
    /** What the test environment signed with before RS256. */
    private const string FORMER_HS256_SECRET = 'ItRAaHgm0QcXr88mxDzC2iSccoXxVPmq2Wp2tGB9A6MtDVVEtjAcv2IUB94ezoTa';

    #[Test]
    public function loginShouldIssueATokenThatNamesTheUserByIdOnly(): void
    {
        $user = UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $payload = static::getContainer()->get(JWTEncoderInterface::class)->decode(
            (string) static::getClient()->getCookieJar()->get('access_token')?->getValue(),
        );
        self::assertSame((string) $user->getId(), $payload['sub']);
        self::assertStringNotContainsString('user@example.com', (string) json_encode($payload));
    }

    #[Test]
    public function tokenShouldStillAuthenticateAfterTheEmailChanges(): void
    {
        $user = UserFactory::createOne(['email' => 'before@example.com']);
        $this->actingAs($user);

        $user->setEmail(new Email('after@example.com'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
        self::assertSame('after@example.com', $this->jsonAttributes($response)['email']);
    }

    #[Test]
    public function tokenShouldNotOpenTheAccountThatTookOverTheEmail(): void
    {
        $alice = UserFactory::createOne(['email' => 'alice@example.com']);
        $this->actingAs($alice);

        $alice->setEmail(new Email('alice.new@example.com'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        UserFactory::createOne(['email' => 'alice@example.com']);

        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
        self::assertSame((string) $alice->getId(), $this->jsonData($response)['id']);
    }

    #[Test]
    public function tokenThatNamesTheUserByEmailShouldBeRefused(): void
    {
        $user = UserFactory::createOne();
        $this->setCookie('access_token', static::getContainer()->get(JWTEncoderInterface::class)->encode([
            'username' => $user->getEmail(),
        ]));

        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function loginShouldIssueATokenSignedWithRs256ThatThePublicKeyVerifies(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $jwt = Configuration::forAsymmetricSigner(
            new RsaSha256(),
            InMemory::plainText('unused'),
            InMemory::file(TestJwtKeys::publicKeyPath()),
        );
        $token = $jwt->parser()->parse((string) static::getClient()->getCookieJar()->get('access_token')?->getValue());
        self::assertInstanceOf(Plain::class, $token);
        self::assertSame('RS256', $token->headers()->get('alg'));
        self::assertTrue($jwt->validator()->validate($token, new SignedWith($jwt->signer(), $jwt->verificationKey())));
    }

    #[Test]
    public function tokenSignedWithThePrivateKeyShouldAuthenticate(): void
    {
        $user = UserFactory::createOne();
        $jwt = Configuration::forAsymmetricSigner(new RsaSha256(), InMemory::file(TestJwtKeys::privateKeyPath()), InMemory::plainText('unused'));
        $this->setCookie('access_token', $this->tokenSignedForUser($jwt, (string) $user->getId())->toString());

        $this->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function tokenSignedWithAnotherPrivateKeyShouldBeRefused(): void
    {
        $user = UserFactory::createOne();
        $anotherKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($anotherKey);
        openssl_pkey_export($anotherKey, $anotherPrivateKey);
        $jwt = Configuration::forAsymmetricSigner(new RsaSha256(), InMemory::plainText($anotherPrivateKey), InMemory::plainText('unused'));
        $this->setCookie('access_token', $this->tokenSignedForUser($jwt, (string) $user->getId())->toString());

        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function tokenSignedWithTheFormerHs256SecretShouldBeRefused(): void
    {
        $user = UserFactory::createOne();
        $jwt = Configuration::forSymmetricSigner(new HmacSha256(), InMemory::plainText(self::FORMER_HS256_SECRET));
        $this->setCookie('access_token', $this->tokenSignedForUser($jwt, (string) $user->getId())->toString());

        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function tokenSignedWithHs256UsingThePublicKeyAsTheSecretShouldBeRefused(): void
    {
        $user = UserFactory::createOne();
        $jwt = Configuration::forSymmetricSigner(new HmacSha256(), InMemory::file(TestJwtKeys::publicKeyPath()));
        $this->setCookie('access_token', $this->tokenSignedForUser($jwt, (string) $user->getId())->toString());

        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function refreshShouldReplaceAFormerHs256TokenWithOneThatAuthenticates(): void
    {
        $user = UserFactory::createOne();
        $jwt = Configuration::forSymmetricSigner(new HmacSha256(), InMemory::plainText(self::FORMER_HS256_SECRET));
        $this->setCookie('access_token', $this->tokenSignedForUser($jwt, (string) $user->getId())->toString());
        $plainRefreshToken = (new RandomRefreshTokenGenerator())->generate();
        RefreshTokenFactory::createOne(['user' => $user, 'token' => RefreshTokenHash::fromPlain($plainRefreshToken)]);
        $this->setCookieWithPath('refresh_token', $plainRefreshToken, '/api/v1/auth');

        $this->post($this->route('api_auth_refresh'));
        self::assertResponseStatusCodeSame(204);

        $this->get($this->route('api_profile_me'));
        self::assertResponseIsSuccessful();
    }

    private function tokenSignedForUser(Configuration $jwt, string $userId): Plain
    {
        $now = new \DateTimeImmutable();

        return $jwt->builder()
            ->relatedTo($userId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($jwt->signer(), $jwt->signingKey());
    }
}
