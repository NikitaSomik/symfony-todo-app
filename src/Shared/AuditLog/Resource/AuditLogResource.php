<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Resource;

use App\Shared\Api\ResourceItem;
use App\Shared\AuditLog\Entity\AuditLog;
use App\Shared\AuditLog\Enum\AuditLogAction;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use App\Task\Enum\TaskStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'audit_logs'),
        new OA\Property(property: 'id', type: 'string', example: '1'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'entity_type', type: 'string', enum: [AuditLogEntityType::TASK->value], example: AuditLogEntityType::TASK->value),
                new OA\Property(property: 'entity_id', type: 'string', format: 'uuid', example: '0195a6b4-6f15-7d4b-b2c1-05b2a3d6e7f8'),
                new OA\Property(property: 'user_id', type: 'integer', example: 7, nullable: true),
                new OA\Property(property: 'action', type: 'string', enum: [AuditLogAction::CREATED->value, AuditLogAction::UPDATED->value, AuditLogAction::DELETED->value], example: AuditLogAction::UPDATED->value),
                new OA\Property(property: 'message', type: 'string', example: 'Updated task title for "Buy almond milk"'),
                new OA\Property(property: 'attribute_changes', properties: [new OA\Property(property: 'old', type: 'object', example: ['title' => 'Buy milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true)), new OA\Property(property: 'attributes', type: 'object', example: ['title' => 'Buy almond milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true))], type: 'object', nullable: true),
                new OA\Property(property: 'properties', type: 'object', example: ['attributes' => ['title' => 'Buy milk', 'status' => TaskStatus::TODO->value]], nullable: true, additionalProperties: new OA\AdditionalProperties()),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
    ]
)]
final class AuditLogResource
{
    public static function toItem(AuditLog $auditLog): ResourceItem
    {
        $id = $auditLog->getId();

        if (null === $id) {
            throw new \LogicException(' AuditLog log resource cannot be created for an entity without id.');
        }

        return new ResourceItem(
            type: 'audit_logs',
            id: $id,
            attributes: [
                'entity_type' => $auditLog->getEntityType()->value,
                'entity_id' => $auditLog->getEntityId(),
                'user_id' => $auditLog->getUser()?->getId(),
                'action' => $auditLog->getAction()->value,
                'message' => $auditLog->getMessage(),
                'attribute_changes' => $auditLog->getAttributeChanges(),
                'properties' => $auditLog->getProperties(),
                'created_at' => $auditLog->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        );
    }

    /**
     * @param AuditLog[] $auditLogs
     *
     * @return ResourceItem[]
     */
    public static function toItems(array $auditLogs): array
    {
        return array_map(self::toItem(...), $auditLogs);
    }
}
