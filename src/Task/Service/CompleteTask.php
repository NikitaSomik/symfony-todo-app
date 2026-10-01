<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompleteTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecordTaskStatusChange $recordStatusChange,
    ) {
    }

    public function handle(Task $task, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $user): Task {
            $previousState = TaskState::fromTask($task);

            $task->complete();
            $this->recordStatusChange->handle($task, $previousState, $user);

            return $task;
        });
    }
}
