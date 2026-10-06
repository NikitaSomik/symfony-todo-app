<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;

final readonly class TaskCreated
{
    public function __construct(
        public string $taskId,
        public int $actorId,
        public TaskState $state,
    ) {
    }

    public static function from(Task $task, int $actorId): self
    {
        return new self(
            taskId: $task->getId()->toRfc4122(),
            actorId: $actorId,
            state: TaskState::fromTask($task),
        );
    }
}
