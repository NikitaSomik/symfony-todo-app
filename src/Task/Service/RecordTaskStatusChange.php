<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use App\Task\Event\TaskStatusChanged;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * What every transition leaves behind: a row in the status history and the event the audit log
 * listens to. A building block of the transition services, so it only persists; the service that
 * calls it owns the transaction.
 */
final readonly class RecordTaskStatusChange
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, TaskState $previousState, User $actor): void
    {
        $from = $previousState->status;
        $to = $task->getStatus();

        $this->em->persist(new TaskStatusChange($task, $from, $to, $this->clock->now()));

        $this->eventDispatcher->dispatch(new TaskStatusChanged(
            taskId: $task->getId()->toRfc4122(),
            actor: $actor,
            from: $from,
            to: $to,
            previousState: $previousState,
            currentState: TaskState::fromTask($task),
        ));
    }
}
