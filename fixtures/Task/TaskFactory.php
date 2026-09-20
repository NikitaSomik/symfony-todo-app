<?php

declare(strict_types=1);

namespace App\Fixtures\Task;

use App\Fixtures\Auth\UserFactory;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

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
            'user' => UserFactory::new(),
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
            $task = new Task(Uuid::v7());
            $task->setTitle($attributes['title']);
            $task->setDescription($attributes['description']);
            $task->changeStatus($attributes['status']);

            if (TaskStatus::CANCELLED === $attributes['status'] && null !== $attributes['cancellationReason']) {
                $task->setCancellationReason($attributes['cancellationReason']);
            }

            $task->setDueDate($attributes['dueDate']);
            $task->setUser($attributes['user']);

            return $task;
        });
    }
}
