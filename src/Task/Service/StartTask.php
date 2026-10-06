<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskStatusChanged;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class StartTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, int $actorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->start($this->clock->now());
            $this->eventDispatcher->dispatch(TaskStatusChanged::from($task, $actorId, $previousState));

            return $task;
        });
    }
}
