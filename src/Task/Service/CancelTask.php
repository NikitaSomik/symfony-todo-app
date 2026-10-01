<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\DTO\CancelTaskDTO;
use App\Task\Entity\Task;
use App\Task\ValueObject\CancellationReason;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CancelTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecordTaskStatusChange $recordStatusChange,
    ) {
    }

    public function handle(Task $task, CancelTaskDTO $dto, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $dto, $user): Task {
            $previousState = TaskState::fromTask($task);

            $task->cancel(new CancellationReason($dto->reason));
            $this->recordStatusChange->handle($task, $previousState, $user);

            return $task;
        });
    }
}
