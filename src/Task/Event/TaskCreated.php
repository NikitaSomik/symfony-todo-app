<?php

declare(strict_types=1);

namespace App\Task\Event;

use App\Auth\Entity\User;
use App\Task\Activity\TaskState;
use App\Task\Entity\Task;

final readonly class TaskCreated
{
    public function __construct(
        public string $taskId,
        public User $actor,
        public TaskState $state,
    ) {
    }

    public static function from(Task $task, User $actor): self
    {
        return new self(
            taskId: $task->getId()->toRfc4122(),
            actor: $actor,
            state: TaskState::fromTask($task),
        );
    }
}
