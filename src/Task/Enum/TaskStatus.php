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

    public function isFinal(): bool
    {
        return self::COMPLETED === $this || self::CANCELLED === $this;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
