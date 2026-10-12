<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Fixtures\Auth\RefreshTokenFactory;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\ConcurrentTokenExchange;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;

// The rival request runs on a connection of its own and sees only committed rows.
#[SkipDatabaseRollback]
final class ConcurrentRefreshTest extends ApiTestCase
{
    private ?int $userId = null;

    protected function tearDown(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM users WHERE id = ?', [$this->userId]);

        parent::tearDown();
    }

    #[Test]
    public function tokenExchangedByTwoRequestsAtOnceShouldServeOnlyOne(): void
    {
        $plainToken = (new RandomRefreshTokenGenerator())->generate();
        $tokenHash = RefreshTokenHash::fromPlain($plainToken);
        $user = UserFactory::createOne();
        $this->userId = $user->getId();
        RefreshTokenFactory::createOne(['token' => $tokenHash, 'user' => $user]);
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $rival = static::getContainer()->get(ConcurrentTokenExchange::class);
        $rival->arm($tokenHash->value);

        $this->setCookie('refresh_token', $plainToken);
        $response = $this->post($this->route('api_auth_refresh'));

        $served = (int) $rival->succeeded() + (int) $response->isSuccessful();
        self::assertSame(1, $served);
    }
}
