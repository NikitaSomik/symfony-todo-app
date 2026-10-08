<?php

declare(strict_types=1);

namespace App\Task\Listener\Workspace;

use App\Task\AuditLog\TaskState;
use App\Task\Event\TaskUpdated;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\MembershipChanged;
use App\Workspace\Contract\WorkspaceRole;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
final readonly class UnassignTasksOfMember
{
    public function __construct(
        private TaskRepository $tasks,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Opens no transaction of its own: a nested one flushes, and a flush per task rechecks
     * every task loaded so far. The change is written by the flush of whoever changed the
     * membership.
     */
    public function __invoke(MembershipChanged $event): void
    {
        if (null !== $event->role && WorkspaceRole::VIEWER !== $event->role) {
            return;
        }

        foreach ($this->tasks->findUnfinishedAssignedTo($event->workspaceId, $event->userId) as $task) {
            $previousState = TaskState::fromTask($task);

            $task->unassign();
            $this->eventDispatcher->dispatch(TaskUpdated::from($task, $event->actorId, $previousState));
        }
    }
}
