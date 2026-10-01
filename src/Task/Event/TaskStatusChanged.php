<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Enum\TaskStatus;

final readonly class TaskStatusChanged
{
    public function __construct(
        public string $taskId,
        public User $actor,
        public TaskStatus $from,
        public TaskStatus $to,
        public TaskState $previousState,
        public TaskState $currentState,
    ) {
    }
}
