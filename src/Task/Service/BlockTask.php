<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\DTO\BlockTaskDTO;
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

    public function handle(Task $task, BlockTaskDTO $dto, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $dto, $user): Task {
            $previousState = TaskState::fromTask($task);

            $task->block(new BlockReason($dto->reason), $this->clock->now());
            $this->eventDispatcher->dispatch(TaskStatusChanged::from($task, $user, $previousState));

            return $task;
        });
    }
}
