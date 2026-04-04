<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Auth\Entity\User;
use App\Task\Activity\TaskState;
use App\Task\Entity\Task;

final readonly class TaskDeleted
{
    public function __construct(
        public int $taskId,
        public User $actor,
        public TaskState $state,
    ) {
    }

    public static function from(Task $task, User $actor): self
    {
        $taskId = $task->getId();

        if (null === $taskId) {
            throw new \LogicException('Task event cannot be created for an entity without id.');
        }

        return new self(
            taskId: $taskId,
            actor: $actor,
            state: TaskState::fromTask($task),
        );
    }
}
