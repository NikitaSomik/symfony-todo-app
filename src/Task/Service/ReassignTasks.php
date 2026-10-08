<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use App\Task\Exception\AssigneeCannotWorkException;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspaceRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ReassignTasks
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private TaskRepository $tasks,
        private WorkspaceAccess $workspaces,
    ) {
    }

    /**
     * @return Task[] the unfinished tasks that changed hands
     */
    public function handle(Uuid $workspaceId, int $fromUserId, int $toUserId, int $actorId): array
    {
        $role = $this->workspaces->roleOf($workspaceId, $toUserId);

        if (null === $role || WorkspaceRole::VIEWER === $role) {
            throw new AssigneeCannotWorkException();
        }

        return $this->em->wrapInTransaction(function () use ($workspaceId, $fromUserId, $toUserId, $actorId): array {
            $tasks = $this->tasks->findUnfinishedAssignedTo($workspaceId, $fromUserId);

            foreach ($tasks as $task) {
                $previousState = TaskState::fromTask($task);

                $task->assignTo($toUserId);
                $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));
            }

            return $tasks;
        });
    }
}
