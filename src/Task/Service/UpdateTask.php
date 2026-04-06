<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\DTO\UpdateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use App\Task\Enum\TaskStatus;
use App\Task\Event\TaskUpdated;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UpdateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, UpdateTaskDTO $dto, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $dto, $user): Task {
            $previousState = TaskState::fromTask($task);
            $previousStatus = $task->getStatus();
            $nextStatus = $dto->status();

            $task->setTitle($dto->title);
            $task->setDescription($dto->description);
            $task->changeStatus($nextStatus);

            if (TaskStatus::CANCELLED === $nextStatus && null !== $dto->cancellation_reason) {
                $task->setCancellationReason($dto->cancellation_reason);
            }

            $task->setDueDate($dto->dueDate());

            if ($previousStatus !== $nextStatus) {
                $this->em->persist(new TaskStatusChange($task, $previousStatus, $nextStatus, $this->clock->now()));
            }

            $this->eventDispatcher->dispatch(TaskUpdated::from(
                task: $task,
                actor: $user,
                previousState: $previousState,
            ));
            $this->em->flush();

            return $task;
        });
    }
}
