<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum Plan: string
{
    case FREE = 'free';
    case TEAM = 'team';
    case BUSINESS = 'business';

    public function memberLimit(): int
    {
        return match ($this) {
            self::FREE => 3,
            self::TEAM => 10,
            self::BUSINESS => 50,
        };
    }
}
