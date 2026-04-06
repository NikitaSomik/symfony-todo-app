<?php

declare(strict_types=1);

namespace App\Shared\Activity\Resource;

use App\Shared\Activity\Entity\ActivityLog;
use App\Shared\Activity\Enum\ActivityAction;
use App\Shared\Activity\Enum\ActivityEntityType;
use App\Shared\Api\ResourceItem;
use App\Task\Enum\TaskStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'activity_logs'),
        new OA\Property(property: 'id', type: 'string', example: '1'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'entity_type', type: 'string', enum: [ActivityEntityType::TASK->value], example: ActivityEntityType::TASK->value),
                new OA\Property(property: 'entity_id', type: 'string', format: 'uuid', example: '0195a6b4-6f15-7d4b-b2c1-05b2a3d6e7f8'),
                new OA\Property(property: 'user_id', type: 'integer', example: 7, nullable: true),
                new OA\Property(property: 'action', type: 'string', enum: [ActivityAction::CREATED->value, ActivityAction::UPDATED->value, ActivityAction::DELETED->value], example: ActivityAction::UPDATED->value),
                new OA\Property(property: 'message', type: 'string', example: 'Updated task title for "Buy almond milk"'),
                new OA\Property(property: 'attribute_changes', properties: [new OA\Property(property: 'old', type: 'object', example: ['title' => 'Buy milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true)), new OA\Property(property: 'attributes', type: 'object', example: ['title' => 'Buy almond milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true))], type: 'object', nullable: true),
                new OA\Property(property: 'properties', type: 'object', example: ['attributes' => ['title' => 'Buy milk', 'status' => TaskStatus::TODO->value]], nullable: true, additionalProperties: new OA\AdditionalProperties()),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
    ]
)]
final class ActivityLogResource
{
    public static function toItem(ActivityLog $activityLog): ResourceItem
    {
        $id = $activityLog->getId();

        if (null === $id) {
            throw new \LogicException('Activity log resource cannot be created for an entity without id.');
        }

        return new ResourceItem(
            type: 'activity_logs',
            id: $id,
            attributes: [
                'entity_type' => $activityLog->getEntityType()->value,
                'entity_id' => $activityLog->getEntityId(),
                'user_id' => $activityLog->getUser()?->getId(),
                'action' => $activityLog->getAction()->value,
                'message' => $activityLog->getMessage(),
                'attribute_changes' => $activityLog->getAttributeChanges(),
                'properties' => $activityLog->getProperties(),
                'created_at' => $activityLog->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        );
    }

    /**
     * @param ActivityLog[] $activityLogs
     *
     * @return ResourceItem[]
     */
    public static function toItems(array $activityLogs): array
    {
        return array_map(self::toItem(...), $activityLogs);
    }
}
