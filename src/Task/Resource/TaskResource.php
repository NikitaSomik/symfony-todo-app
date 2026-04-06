<?php

declare(strict_types=1);

namespace App\Task\Resource;

use App\Shared\Api\ResourceItem;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'tasks'),
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
                new OA\Property(property: 'description', type: 'string', example: '2 liters', nullable: true),
                new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), description: 'Task status'),
                new OA\Property(property: 'cancellation_reason', type: 'string', example: 'Task is no longer needed', nullable: true),
                new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
    ]
)]
final class TaskResource
{
    public static function toItem(Task $task): ResourceItem
    {
        return new ResourceItem(
            type: 'tasks',
            id: $task->getId()->toRfc4122(),
            attributes: [
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus()->value,
                'cancellation_reason' => $task->getCancellationReason(),
                'due_date' => $task->getDueDate()?->format('Y-m-d'),
                'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ],
        );
    }

    /**
     * @param Task[] $tasks
     *
     * @return ResourceItem[]
     */
    public static function toItems(array $tasks): array
    {
        return array_map(self::toItem(...), $tasks);
    }
}
