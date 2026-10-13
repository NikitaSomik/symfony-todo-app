<?php

declare(strict_types=1);

namespace App\Task\Query;

final readonly class AssigneeFilter
{
    private function __construct(
        public ?int $userId,
    ) {
    }

    public static function assignedTo(int $userId): self
    {
        return new self($userId);
    }

    public static function unassigned(): self
    {
        return new self(null);
    }
}
