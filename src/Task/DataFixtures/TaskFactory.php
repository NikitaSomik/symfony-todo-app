<?php

declare(strict_types=1);

namespace App\Task\DataFixtures;

use App\Auth\DataFixtures\UserFactory;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
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
            'status' => TaskStatus::TODO,
            'cancellationReason' => null,
            'dueDate' => null,
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
        return $this;
    }
}
