<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Repository\RefreshTokenRepository;

final class RevokeRefreshTokens
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    /**
     * Revokes every refresh session of the token owner, not only the presented one.
     * Unknown or expired tokens are ignored.
     */
    public function handle(string $plainToken): void
    {
        $refreshToken = $this->refreshTokenRepository->findValidByToken($plainToken);

        if (null !== $refreshToken) {
            $this->refreshTokenRepository->deleteAllForUser($refreshToken->getUser());
        }
    }
}
