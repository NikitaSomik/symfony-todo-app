<?php

declare(strict_types=1);

namespace App\Auth\RefreshToken;

/**
 * Plain refresh token handed to the client right after issuing.
 * Only its hash is stored, so this is the one moment the value is known.
 */
final readonly class IssuedRefreshToken
{
    public function __construct(
        public string $value,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
