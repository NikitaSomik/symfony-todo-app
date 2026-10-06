<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskStatusChanged;
use App\Task\ValueObject\BlockReason;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class BlockTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, BlockReason $reason, int $actorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $reason, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->block($reason, $this->clock->now());
            $this->eventDispatcher->dispatch(TaskStatusChanged::from($task, $actorId, $previousState));

            return $task;
        });
    }
}
