<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\ValueObject\Email;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;

final class AccessTokenTest extends ApiTestCase
{
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
}
