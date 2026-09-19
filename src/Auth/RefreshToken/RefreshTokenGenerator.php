<?php

declare(strict_types=1);

namespace App\Auth\RefreshToken;

interface RefreshTokenGenerator
{
    public function generate(): string;
}
