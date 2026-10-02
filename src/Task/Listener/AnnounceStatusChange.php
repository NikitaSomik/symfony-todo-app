<?php

declare(strict_types=1);

namespace App\Task\Listener;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Event\TaskStatusChanged;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Turns the workflow's own event into the module's TaskStatusChanged, so the audit log and any
 * later subscriber keep depending on the module rather than on the workflow component.
 */
final readonly class AnnounceStatusChange
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /** @param CompletedEvent<Task> $event */
    #[AsCompletedListener('task_lifecycle')]
    public function __invoke(CompletedEvent $event): void
    {
        $task = $event->getSubject();
        $transition = $event->getTransition();
        $actor = $event->getContext()['actor'] ?? null;
        \assert(null !== $transition && $actor instanceof User);

        $currentState = TaskState::fromTask($task);
        // A transition changes the status and, when cancelling, the reason; the rest of the task is as it was.
        $previousState = new TaskState(
            title: $currentState->title,
            description: $currentState->description,
            status: TaskStatus::from($transition->getFroms()[0]),
            cancellationReason: null,
            dueDate: $currentState->dueDate,
        );

        $this->eventDispatcher->dispatch(new TaskStatusChanged($task->getId()->toRfc4122(), $actor, $previousState, $currentState));
    }
}
