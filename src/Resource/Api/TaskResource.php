<?php

declare(strict_types=1);

namespace App\Resource\Api;

use App\Entity\Task;
use App\Enum\TaskStatus;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
        new OA\Property(property: 'description', type: 'string', example: '2 liters', nullable: true),
        new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), description: 'Task status'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
final class TaskResource
{
    /**
     * @return array{id: ?int, title: string, description: ?string, status: string, created_at: string, updated_at: string}
     */
    public static function fromEntity(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->value,
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param Task[] $tasks
     *
     * @return list<array{id: ?int, title: string, description: ?string, status: string, created_at: string, updated_at: string}>
     */
    public static function fromCollection(array $tasks): array
    {
        return array_map(self::fromEntity(...), $tasks);
    }
}
