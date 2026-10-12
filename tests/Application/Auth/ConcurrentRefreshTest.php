<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Tests\ApiTestCase;
use App\Tests\Support\ConcurrentTokenExchange;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;

/**
 * The rival request runs on a connection of its own and sees only committed rows,
 * so the user and the tokens are committed beside the test's transaction and removed after each test.
 */
final class ConcurrentRefreshTest extends ApiTestCase
{
    private static ?Connection $committed = null;
    private ?int $userId = null;

    protected function tearDown(): void
    {
        // The request's transaction keeps the rows it touched locked until it is rolled back,
        // which the bundle would only do when the next test starts.
        StaticDriver::rollBack();
        $this->committed()->executeStatement('DELETE FROM users WHERE id = ?', [$this->userId]);
        StaticDriver::beginTransaction();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        self::$committed?->close();
        self::$committed = null;

        parent::tearDownAfterClass();
    }

    #[Test]
    public function tokenExchangedByTwoRequestsAtOnceShouldServeOnlyOne(): void
    {
        $userId = $this->createUser();
        [$plainToken, $tokenHash] = $this->createRefreshToken($userId);

        $rival = static::getContainer()->get(ConcurrentTokenExchange::class);
        $rival->arm($tokenHash);

        $this->setCookie('refresh_token', $plainToken);
        $response = $this->post($this->route('api_auth_refresh'));

        $served = (int) $rival->succeeded() + (int) $response->isSuccessful();
        self::assertSame(1, $served);
    }

    #[Test]
    public function exchangeShouldNotHoldUpAnotherDeviceOfTheSameUser(): void
    {
        $userId = $this->createUser();
        [$plainToken] = $this->createRefreshToken($userId);
        [, $otherDeviceTokenHash] = $this->createRefreshToken($userId);

        $rival = static::getContainer()->get(ConcurrentTokenExchange::class);
        $rival->arm($otherDeviceTokenHash);

        $this->setCookie('refresh_token', $plainToken);
        $this->post($this->route('api_auth_refresh'));

        self::assertTrue($rival->succeeded());
    }

    private function createUser(): int
    {
        $this->committed()->insert('users', [
            'email' => 'concurrent-refresh@example.com',
            'password' => 'not-used',
            'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'updated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);

        return $this->userId = (int) $this->committed()->lastInsertId();
    }

    /** @return array{string, string} the plain token and its stored hash */
    private function createRefreshToken(int $userId): array
    {
        $plainToken = (new RandomRefreshTokenGenerator())->generate();
        $tokenHash = RefreshTokenHash::fromPlain($plainToken)->value;

        $this->committed()->insert('refresh_tokens', [
            'token' => $tokenHash,
            'user_id' => $userId,
            'expires_at' => (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM),
            'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);

        return [$plainToken, $tokenHash];
    }

    private function committed(): Connection
    {
        return self::$committed ??= DriverManager::getConnection(static::getContainer()->get(Connection::class)->getParams());
    }
}
