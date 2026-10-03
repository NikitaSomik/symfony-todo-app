<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskStatusChanged;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class UnblockTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $user): Task {
            $previousState = TaskState::fromTask($task);

            $task->unblock($this->clock->now());
            $this->eventDispatcher->dispatch(TaskStatusChanged::from($task, $user, $previousState));

            return $task;
        });
    }
}
