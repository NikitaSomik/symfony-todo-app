<?php

declare(strict_types=1);

namespace App\Auth\Contract;

final readonly class UserRegistered
{
    public function __construct(
        public int $userId,
    ) {
    }
}
