<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use App\Task\ValueObject\TaskDetails;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UpdateTaskDetails
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, TaskDetails $details, int $actorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $details, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->setTitle($details->title);
            $task->setDescription($details->description);
            $task->setDueDate($details->dueDate);

            $this->eventDispatcher->dispatch(TaskUpdated::from(
                task: $task,
                actorId: $actorId,
                previousState: $previousState,
            ));

            return $task;
        });
    }
}
