<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class RevokeRefreshTokens
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Ends the session the token belongs to; the user's other devices stay signed in. */
    public function handle(string $plainToken): void
    {
        $now = $this->clock->now();
        $refreshToken = $this->refreshTokenRepository->findValidByToken(RefreshTokenHash::fromPlain($plainToken), $now);

        if (null === $refreshToken) {
            return;
        }

        $refreshToken->getFamily()->revoke($now);
        $this->em->flush();
    }
}
