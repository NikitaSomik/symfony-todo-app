<?php

declare(strict_types=1);

namespace App\Tests\Application\Logging;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;

final class RejectedAccessTokenLoggingTest extends ApiTestCase
{
    private function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    private function requestWithToken(string $token): void
    {
        $this->setCookie('access_token', $token);
        $this->get($this->route('api_profile_me'));
        self::assertResponseStatusCodeSame(401);
    }

    /** @return list<array<mixed>> */
    private function rejections(): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return array_values(array_map(
            static fn (LogRecord $record): array => $record->context,
            array_filter(
                $logs->getRecords(),
                static fn (LogRecord $record): bool => 'security' === $record->channel && 'Access token rejected.' === $record->message && Level::Warning === $record->level,
            ),
        ));
    }

    private function assertRejectedBecause(string $reason): void
    {
        $rejections = $this->rejections();

        self::assertCount(1, $rejections);
        self::assertSame($reason, $rejections[0]['reason']);
    }

    #[Test]
    public function malformedTokenShouldBeLogged(): void
    {
        $this->requestWithToken('not-a-jwt');

        $this->assertRejectedBecause('malformed');
    }

    #[Test]
    public function tokenWithAForeignSignatureShouldBeLogged(): void
    {
        [$header, $payload] = explode('.', $this->tokenFor(UserFactory::createOne()));
        [, , $signatureOfAnotherToken] = explode('.', $this->tokenFor(UserFactory::createOne()));

        $this->requestWithToken(implode('.', [$header, $payload, $signatureOfAnotherToken]));

        $this->assertRejectedBecause('bad_signature');
    }

    #[Test]
    public function tokenRevokedAtLogoutShouldBeLogged(): void
    {
        $token = $this->tokenFor(UserFactory::createOne());
        $this->setCookie('access_token', $token);
        $this->post($this->route('api_auth_logout'));
        self::assertResponseStatusCodeSame(204);

        $this->requestWithToken($token);

        $this->assertRejectedBecause('revoked');
    }

    #[Test]
    public function tokenOfAUserWhoDoesNotExistShouldBeLogged(): void
    {
        $this->requestWithToken(static::getContainer()->get(JWTEncoderInterface::class)->encode(['username' => 'nobody@example.com']));

        $this->assertRejectedBecause('user_not_found');
    }

    #[Test]
    public function tokenThatNamesNoUserShouldBeLogged(): void
    {
        $this->requestWithToken(static::getContainer()->get(JWTEncoderInterface::class)->encode(['role' => 'none']));

        $this->assertRejectedBecause('invalid_payload');
    }

    #[Test]
    public function expiredTokenShouldNotBeLogged(): void
    {
        $user = UserFactory::createOne();
        $this->requestWithToken(static::getContainer()->get(JWTEncoderInterface::class)->encode([
            'username' => $user->getEmail(),
            'iat' => time() - 120,
            'exp' => time() - 60,
        ]));

        self::assertSame([], $this->rejections());
    }

    #[Test]
    public function requestWithoutTokenShouldNotBeLogged(): void
    {
        $this->get($this->route('api_profile_me'));
        self::assertResponseStatusCodeSame(401);

        self::assertSame([], $this->rejections());
    }
}
