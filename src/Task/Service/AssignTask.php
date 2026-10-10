<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Shared\Messaging\EventPublisher;
use App\Task\AuditLog\TaskState;
use App\Task\Contract\TaskAssigned;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use App\Task\Exception\AssigneeCannotWorkException;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AssignTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private WorkspaceAccess $workspaces,
        private EventPublisher $events,
    ) {
    }

    public function handle(Task $task, int $assigneeId, int $actorId): Task
    {
        if (!$this->workspaces->can($task->getWorkspaceId(), $assigneeId, WorkspacePermission::WORK_ON_TASKS)) {
            throw new AssigneeCannotWorkException();
        }

        $previousAssigneeId = $task->getAssigneeId();

        $this->em->wrapInTransaction(function () use ($task, $assigneeId, $actorId): void {
            $previousState = TaskState::fromTask($task);

            $task->assignTo($assigneeId);
            $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));
        });

        if ($previousAssigneeId !== $assigneeId) {
            $this->events->publish(new TaskAssigned($task->getId(), $task->getWorkspaceId(), assigneeId: $assigneeId, actorId: $actorId));
        }

        return $task;
    }
}
