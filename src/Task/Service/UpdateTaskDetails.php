<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UpdateTaskDetails
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, string $title, ?string $description, ?\DateTimeImmutable $dueDate, int $actorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $title, $description, $dueDate, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->setTitle($title);
            $task->setDescription($description);
            $task->setDueDate($dueDate);

            $this->eventDispatcher->dispatch(TaskUpdated::from(
                task: $task,
                actorId: $actorId,
                previousState: $previousState,
            ));

            return $task;
        });
    }
}
