<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum Plan: string
{
    case FREE = 'free';
    case TEAM = 'team';
    case BUSINESS = 'business';

    public const int TEAM_WORKSPACES_ON_FREE = 1;

    public function memberLimit(): int
    {
        return match ($this) {
            self::FREE => 3,
            self::TEAM => 10,
            self::BUSINESS => 50,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** The plan a team moves to when it outgrows this one; the last one has none. */
    public function next(): ?self
    {
        return match ($this) {
            self::FREE => self::TEAM,
            self::TEAM => self::BUSINESS,
            self::BUSINESS => null,
        };
    }
}
