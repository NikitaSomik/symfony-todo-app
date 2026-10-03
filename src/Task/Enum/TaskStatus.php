<?php

declare(strict_types=1);

namespace App\Task\Enum;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case BLOCKED = 'blocked';
    case IN_REVIEW = 'in_review';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::TODO => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::IN_REVIEW, self::BLOCKED, self::CANCELLED],
            self::BLOCKED => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_REVIEW => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
        }, true);
    }
}
