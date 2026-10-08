<?php

declare(strict_types=1);

namespace App\Fixtures\Task;

use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Workspace\Entity\Workspace;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\lazy;
use function Zenstruck\Foundry\set;

/**
 * @extends PersistentObjectFactory<Task>
 */
final class TaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Task::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->sentence(3),
            'description' => self::faker()->optional()->sentence(),
            'dueDate' => null,
            'status' => TaskStatus::TODO,
            'cancellationReason' => null,
            'blockReason' => null,
            'createdAt' => new \DateTimeImmutable(),
            'workspace' => lazy(static fn (): Workspace => WorkspaceFactory::createOne()),
            // Null means the owner of the workspace.
            'creatorId' => null,
            'assigneeId' => null,
        ];
    }

    public function completed(): static
    {
        return $this->with(['status' => TaskStatus::COMPLETED]);
    }

    public function inProgress(): static
    {
        return $this->with(['status' => TaskStatus::IN_PROGRESS]);
    }

    public function blocked(?string $reason = 'Blocked by fixture'): static
    {
        return $this->with([
            'status' => TaskStatus::BLOCKED,
            'blockReason' => $reason,
        ]);
    }

    public function inReview(): static
    {
        return $this->with(['status' => TaskStatus::IN_REVIEW]);
    }

    public function cancelled(?string $reason = 'Task cancelled by fixture'): static
    {
        return $this->with([
            'status' => TaskStatus::CANCELLED,
            'cancellationReason' => $reason,
        ]);
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(function (array $attributes): Task {
            $workspace = $attributes['workspace'];
            \assert($workspace instanceof Workspace);

            $task = new Task(id: Uuid::v7(), workspaceId: $workspace->getId(), creatorId: $attributes['creatorId'] ?? $workspace->getMembers()[0]->getUserId());
            $task->setTitle($attributes['title']);
            $task->setDescription($attributes['description']);
            $task->setDueDate($attributes['dueDate']);
            // A task reaches a status only through its lifecycle and sets its creation time itself;
            // tests need a task in any of those states without replaying how it got there.
            set($task, 'status', $attributes['status']);
            set($task, 'cancellationReason', $attributes['cancellationReason']);
            set($task, 'blockReason', $attributes['blockReason']);
            set($task, 'createdAt', $attributes['createdAt']);
            set($task, 'assigneeId', $attributes['assigneeId']);

            return $task;
        });
    }
}
