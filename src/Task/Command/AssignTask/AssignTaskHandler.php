<?php

declare(strict_types=1);

namespace App\Task\Command\AssignTask;

use App\Task\AuditLog\TaskState;
use App\Task\Contract\TaskAssigned;
use App\Task\Event\TaskUpdated;
use App\Task\Exception\AssigneeCannotWorkException;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AssignTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private WorkspaceAccess $workspaces,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AssignTask $command): void
    {
        $task = $this->tasks->find($command->taskId) ?? throw new \LogicException('The controller resolves the task before assigning it.');

        if (!$this->workspaces->can($task->getWorkspaceId(), $command->assigneeId, WorkspacePermission::WORK_ON_TASKS)) {
            throw new AssigneeCannotWorkException();
        }

        $previousState = TaskState::fromTask($task);
        $task->assignTo($command->assigneeId);
        $this->eventDispatcher->dispatch(TaskUpdated::from($task, $command->actorId, $previousState));

        if ($previousState->assigneeId !== $command->assigneeId) {
            $this->eventBus->dispatch(
                new TaskAssigned($task->getId(), $task->getWorkspaceId(), assigneeId: $command->assigneeId, actorId: $command->actorId),
                [new DispatchAfterCurrentBusStamp()],
            );
        }
    }
}
