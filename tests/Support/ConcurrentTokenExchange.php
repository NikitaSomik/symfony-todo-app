<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Entity\RefreshToken;
use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Plays a second request that exchanges a refresh token on its own connection,
 * right after the first one has read its token: the moment two browser tabs collide.
 */
final class ConcurrentTokenExchange
{
    private const string LOCK_NOT_AVAILABLE = '55P03';

    private ?string $tokenHash = null;
    private ?bool $succeeded = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function arm(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function succeeded(): ?bool
    {
        return $this->succeeded;
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        if (null === $this->tokenHash || !$args->getObject() instanceof RefreshToken) {
            return;
        }

        $tokenHash = $this->tokenHash;
        $this->tokenHash = null;

        $rival = DriverManager::getConnection($this->connection->getParams());
        // The first request is paused inside this callback, so a wait would never end.
        $rival->executeStatement("SET lock_timeout = '200ms'");

        try {
            $this->succeeded = $rival->transactional(static function (Connection $rival) use ($tokenHash): bool {
                $familyId = $rival->fetchOne('UPDATE refresh_tokens SET used_at = NOW() WHERE token = ? AND used_at IS NULL RETURNING family_id', [$tokenHash]);
                if (false === $familyId) {
                    return false;
                }

                $rival->insert('refresh_tokens', [
                    'token' => RefreshTokenHash::fromPlain((new RandomRefreshTokenGenerator())->generate())->value,
                    'family_id' => $familyId,
                    'expires_at' => (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM),
                    'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                ]);

                return true;
            });
        } catch (DriverException $e) {
            if (self::LOCK_NOT_AVAILABLE !== $e->getSQLState()) {
                throw $e;
            }
            $this->succeeded = false;
        } finally {
            $rival->close();
        }
    }
}
