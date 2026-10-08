<?php

declare(strict_types=1);

namespace App\Task\Listener\Workspace;

use App\Task\AuditLog\TaskState;
use App\Task\Event\TaskUpdated;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\MemberPermissionsChanged;
use App\Workspace\Contract\MembershipEnded;
use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class UnassignTasksOfMember
{
    public function __construct(
        private TaskRepository $tasks,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[AsEventListener]
    public function membershipEnded(MembershipEnded $event): void
    {
        $this->unassign($event->workspaceId, userId: $event->userId, actorId: $event->actorId);
    }

    #[AsEventListener]
    public function permissionsChanged(MemberPermissionsChanged $event): void
    {
        if ($event->memberCan(WorkspacePermission::WORK_ON_TASKS)) {
            return;
        }

        $this->unassign($event->workspaceId, userId: $event->userId, actorId: $event->actorId);
    }

    /**
     * Opens no transaction of its own: a nested one flushes, and a flush per task rechecks
     * every task loaded so far. The change is written by the flush of whoever changed the
     * membership.
     */
    private function unassign(Uuid $workspaceId, int $userId, int $actorId): void
    {
        foreach ($this->tasks->findUnfinishedAssignedTo($workspaceId, $userId) as $task) {
            $previousState = TaskState::fromTask($task);

            $task->unassign();
            $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));
        }
    }
}
