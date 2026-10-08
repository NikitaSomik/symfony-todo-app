<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use App\Task\Exception\AssigneeCannotWorkException;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspaceRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AssignTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private WorkspaceAccess $workspaces,
    ) {
    }

    public function handle(Task $task, int $assigneeId, int $actorId): Task
    {
        $role = $this->workspaces->roleOf($task->getWorkspaceId(), $assigneeId);

        if (null === $role || WorkspaceRole::VIEWER === $role) {
            throw new AssigneeCannotWorkException();
        }

        return $this->em->wrapInTransaction(function () use ($task, $assigneeId, $actorId): Task {
            $previousState = TaskState::fromTask($task);

            $task->assignTo($assigneeId);
            $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));

            return $task;
        });
    }
}
