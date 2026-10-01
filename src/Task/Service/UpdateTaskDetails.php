<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\DTO\UpdateTaskDetailsDTO;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** Changes what a task says. Its status moves only through the transition services. */
final class UpdateTaskDetails
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, UpdateTaskDetailsDTO $dto, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $dto, $user): Task {
            $previousState = TaskState::fromTask($task);

            $task->setTitle($dto->title);
            $task->setDescription($dto->description);
            $task->setDueDate($dto->dueDate());

            $this->eventDispatcher->dispatch(TaskUpdated::from(
                task: $task,
                actor: $user,
                previousState: $previousState,
            ));

            return $task;
        });
    }
}
