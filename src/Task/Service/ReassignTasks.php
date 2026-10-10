<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Shared\Messaging\EventPublisher;
use App\Task\AuditLog\TaskState;
use App\Task\Contract\TaskAssigned;
use App\Task\Entity\Task;
use App\Task\Event\TaskUpdated;
use App\Task\Exception\AssigneeCannotWorkException;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
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
        private EventPublisher $events,
    ) {
    }

    /**
     * @return Task[] the unfinished tasks that changed hands
     */
    public function handle(Uuid $workspaceId, int $fromUserId, int $toUserId, int $actorId): array
    {
        if (!$this->workspaces->can($workspaceId, $toUserId, WorkspacePermission::WORK_ON_TASKS)) {
            throw new AssigneeCannotWorkException();
        }

        $tasks = $this->em->wrapInTransaction(function () use ($workspaceId, $fromUserId, $toUserId, $actorId): array {
            $tasks = $this->tasks->findUnfinishedAssignedTo($workspaceId, $fromUserId);

            foreach ($tasks as $task) {
                $previousState = TaskState::fromTask($task);

                $task->assignTo($toUserId);
                $this->eventDispatcher->dispatch(TaskUpdated::from($task, $actorId, $previousState));
            }

            return $tasks;
        });

        foreach ($tasks as $task) {
            $this->events->publish(new TaskAssigned($task->getId(), $task->getWorkspaceId(), assigneeId: $toUserId, actorId: $actorId));
        }

        return $tasks;
    }
}
