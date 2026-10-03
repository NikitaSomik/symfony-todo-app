<?php

declare(strict_types=1);

namespace App\Task\Enum;

enum TaskTransition: string
{
    case START = 'start';
    case SUBMIT_FOR_REVIEW = 'submit_for_review';
    case COMPLETE = 'complete';
    case BLOCK = 'block';
    case UNBLOCK = 'unblock';
    case CANCEL = 'cancel';

    /** @return list<TaskStatus> */
    public function fromStatuses(): array
    {
        return match ($this) {
            self::START => [TaskStatus::TODO],
            self::SUBMIT_FOR_REVIEW, self::BLOCK => [TaskStatus::IN_PROGRESS],
            self::COMPLETE => [TaskStatus::IN_REVIEW],
            self::UNBLOCK => [TaskStatus::BLOCKED],
            self::CANCEL => [TaskStatus::TODO, TaskStatus::IN_PROGRESS, TaskStatus::BLOCKED, TaskStatus::IN_REVIEW],
        };
    }

    public function toStatus(): TaskStatus
    {
        return match ($this) {
            self::START, self::UNBLOCK => TaskStatus::IN_PROGRESS,
            self::SUBMIT_FOR_REVIEW => TaskStatus::IN_REVIEW,
            self::COMPLETE => TaskStatus::COMPLETED,
            self::BLOCK => TaskStatus::BLOCKED,
            self::CANCEL => TaskStatus::CANCELLED,
        };
    }

    public function isAllowedFrom(TaskStatus $status): bool
    {
        return in_array($status, $this->fromStatuses(), true);
    }

    /** @return list<self> */
    public static function availableFrom(TaskStatus $status): array
    {
        return array_values(array_filter(self::cases(), static fn (self $transition): bool => $transition->isAllowedFrom($status)));
    }
}
