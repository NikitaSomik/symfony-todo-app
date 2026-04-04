<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Auth\Entity\User;
use App\Task\Activity\TaskState;
use App\Task\Entity\Task;

final readonly class TaskUpdated
{
    public function __construct(
        public int $taskId,
        public User $actor,
        public TaskState $previousState,
        public TaskState $currentState,
    ) {
    }

    public static function from(Task $task, User $actor, TaskState $previousState): self
    {
        $taskId = $task->getId();

        if (null === $taskId) {
            throw new \LogicException('Task event cannot be created for an entity without id.');
        }

        return new self(
            taskId: $taskId,
            actor: $actor,
            previousState: $previousState,
            currentState: TaskState::fromTask($task),
        );
    }
}
