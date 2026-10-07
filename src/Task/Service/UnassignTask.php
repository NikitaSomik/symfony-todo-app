<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class UnassignTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, int $actorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->unassign();
            $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));

            return $task;
        });
    }
}
