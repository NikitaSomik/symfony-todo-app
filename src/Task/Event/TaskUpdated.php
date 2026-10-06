<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;

final readonly class TaskUpdated
{
    public function __construct(
        public string $taskId,
        public int $actorId,
        public TaskState $previousState,
        public TaskState $currentState,
    ) {
    }

    public static function from(Task $task, int $actorId, TaskState $previousState): self
    {
        return new self(
            taskId: $task->getId()->toRfc4122(),
            actorId: $actorId,
            previousState: $previousState,
            currentState: TaskState::fromTask($task),
        );
    }
}
