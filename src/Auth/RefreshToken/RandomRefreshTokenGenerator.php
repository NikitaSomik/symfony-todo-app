<?php

declare(strict_types=1);

namespace App\Auth\RefreshToken;

final readonly class RandomRefreshTokenGenerator implements RefreshTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
