<?php

declare(strict_types=1);

namespace App\Auth\RefreshToken;

/**
 * SHA-256 of a plain refresh token: the only form in which a token is stored or looked up.
 * A fast hash is enough because the token is 256 bits of randomness, and it keeps lookups indexable.
 */
final readonly class RefreshTokenHash
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromPlain(string $plainToken): self
    {
        return new self(hash('sha256', $plainToken));
    }
}
