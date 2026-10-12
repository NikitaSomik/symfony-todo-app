<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Entity\RefreshToken;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Plays a second request that exchanges the same refresh token on its own connection,
 * right after the first one has read it: the moment two browser tabs collide.
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
            $this->succeeded = 1 === $rival->executeStatement('DELETE FROM refresh_tokens WHERE token = ?', [$tokenHash]);
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
