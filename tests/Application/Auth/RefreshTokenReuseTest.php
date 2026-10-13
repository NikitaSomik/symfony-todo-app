<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\Entity\RefreshTokenFamily;
use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Fixtures\Auth\RefreshTokenFactory;
use App\Fixtures\Auth\RefreshTokenFamilyFactory;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class RefreshTokenReuseTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime();
    }

    #[Test]
    public function tokenPresentedAgainAfterTheReuseWindowShouldBeRefused(): void
    {
        $family = RefreshTokenFamilyFactory::createOne();
        $stolen = $this->tokenIn($family, usedAt: $this->secondsAgo(60));

        self::assertSame(401, $this->refresh($stolen)->getStatusCode());
    }

    #[Test]
    public function tokenPresentedAgainAfterTheReuseWindowShouldEndItsSession(): void
    {
        $family = RefreshTokenFamilyFactory::createOne();
        $stolen = $this->tokenIn($family, usedAt: $this->secondsAgo(60));
        $latest = $this->tokenIn($family);

        $this->refresh($stolen);

        self::assertSame(401, $this->refresh($latest)->getStatusCode());
    }

    #[Test]
    public function tokenPresentedAgainAfterTheReuseWindowShouldLeaveOtherSessionsOfTheUser(): void
    {
        $user = UserFactory::createOne();
        $stolen = $this->tokenIn(RefreshTokenFamilyFactory::createOne(['user' => $user]), usedAt: $this->secondsAgo(60));
        $otherDevice = $this->tokenIn(RefreshTokenFamilyFactory::createOne(['user' => $user]));

        $this->refresh($stolen);

        self::assertSame(204, $this->refresh($otherDevice)->getStatusCode());
    }

    #[Test]
    public function tokenPresentedAgainAfterTheReuseWindowShouldBeLoggedAsAWarning(): void
    {
        $family = RefreshTokenFamilyFactory::createOne();
        $stolen = $this->tokenIn($family, usedAt: $this->secondsAgo(60));

        $this->fromIp('203.0.113.7')->refresh($stolen);

        $reuses = $this->securityRecords('Refresh token reused, session revoked.');
        self::assertCount(1, $reuses);
        self::assertSame(Level::Warning, $reuses[0]->level);
        self::assertSame([
            'user_id' => $family->getUser()->getId(),
            'family_id' => $family->getId()->toRfc4122(),
            'ip' => '203.0.113.7',
        ], $reuses[0]->context);
    }

    #[Test]
    public function tokenPresentedAgainAtTheEdgeOfTheReuseWindowShouldEarnANewToken(): void
    {
        $family = RefreshTokenFamilyFactory::createOne();
        $secondTab = $this->tokenIn($family, usedAt: $this->secondsAgo(30));
        $latest = $this->tokenIn($family);

        self::assertSame(204, $this->refresh($secondTab)->getStatusCode());
        self::assertSame(204, $this->refresh($latest)->getStatusCode());
        self::assertSame([], $this->securityRecords('Refresh token reused, session revoked.'));
    }

    #[Test]
    public function tokenPresentedAgainJustPastTheReuseWindowShouldBeRefused(): void
    {
        $stolen = $this->tokenIn(RefreshTokenFamilyFactory::createOne(), usedAt: $this->secondsAgo(31));

        self::assertSame(401, $this->refresh($stolen)->getStatusCode());
    }

    #[Test]
    public function exchangeShouldKeepTheTokenMarkedUsed(): void
    {
        $token = $this->tokenIn(RefreshTokenFamilyFactory::createOne());

        $this->refresh($token);

        $stored = RefreshTokenFactory::repository()->findOneBy(['token' => RefreshTokenHash::fromPlain($token)->value]);
        self::assertNotNull($stored?->getUsedAt());
    }

    #[Test]
    public function sessionPastItsLifetimeShouldBeRefusedHoweverFreshTheToken(): void
    {
        $token = $this->tokenIn(RefreshTokenFamilyFactory::new()->expired()->create());

        self::assertSame(401, $this->refresh($token)->getStatusCode());
    }

    #[Test]
    public function tokenShouldNotOutliveItsSession(): void
    {
        $sessionEnd = new \DateTimeImmutable('+2 days');
        $token = $this->tokenIn(RefreshTokenFamilyFactory::createOne(['expiresAt' => $sessionEnd]));

        $response = $this->refresh($token);

        self::assertLessThanOrEqual($sessionEnd->getTimestamp(), $this->refreshCookie($response)->getExpiresTime());
    }

    #[Test]
    public function eachSignInShouldStartASessionOfItsOwn(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => 'password']);
        $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => 'password']);

        self::assertSame(2, RefreshTokenFamilyFactory::count());
    }

    private function secondsAgo(int $seconds): \DateTimeImmutable
    {
        return Clock::get()->now()->modify(sprintf('-%d seconds', $seconds));
    }

    private function tokenIn(RefreshTokenFamily $family, ?\DateTimeImmutable $usedAt = null): string
    {
        $plainToken = (new RandomRefreshTokenGenerator())->generate();
        $factory = RefreshTokenFactory::new(['token' => RefreshTokenHash::fromPlain($plainToken), 'family' => $family]);
        (null === $usedAt ? $factory : $factory->used($usedAt))->create();

        return $plainToken;
    }

    private function refresh(string $token): Response
    {
        // A browser holds one refresh cookie: drop the one the previous response set.
        $this->clearCookies()->setCookieWithPath('refresh_token', $token, '/api/v1/auth');

        return $this->post($this->route('api_auth_refresh'));
    }

    private function refreshCookie(Response $response): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ('refresh_token' === $cookie->getName()) {
                return $cookie;
            }
        }

        self::fail('No refresh_token cookie in the response.');
    }

    /** @return list<LogRecord> */
    private function securityRecords(string $message): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return array_values(array_filter(
            $logs->getRecords(),
            static fn (LogRecord $record): bool => 'security' === $record->channel && $message === $record->message,
        ));
    }
}
