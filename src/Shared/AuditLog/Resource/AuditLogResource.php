<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Resource;

use App\Auth\Resource\UserResource;
use App\Shared\Api\ResourceItem;
use App\Shared\AuditLog\Entity\AuditLog;
use App\Shared\AuditLog\Enum\AuditLogAction;
use App\Task\Enum\TaskStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'audit_logs'),
        new OA\Property(property: 'id', type: 'string', example: '1'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'action', type: 'string', enum: [AuditLogAction::CREATED->value, AuditLogAction::UPDATED->value, AuditLogAction::DELETED->value], example: AuditLogAction::UPDATED->value),
                new OA\Property(property: 'message', type: 'string', example: 'Updated task title for "Buy almond milk"'),
                new OA\Property(property: 'attribute_changes', properties: [new OA\Property(property: 'old', type: 'object', example: ['title' => 'Buy milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true)), new OA\Property(property: 'new', type: 'object', example: ['title' => 'Buy almond milk'], additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true))], type: 'object', nullable: true),
                new OA\Property(property: 'metadata', type: 'object', example: ['entity_data' => ['title' => 'Buy milk', 'status' => TaskStatus::TODO->value]], nullable: true, additionalProperties: new OA\AdditionalProperties()),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'relationships',
            properties: [
                new OA\Property(
                    property: 'user',
                    description: 'Who made the change; null when the user no longer exists',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: UserResource::TYPE), new OA\Property(property: 'id', type: 'string', example: '7')], type: 'object', nullable: true)],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'entity',
                    description: 'The audited resource',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: 'tasks'), new OA\Property(property: 'id', type: 'string', example: '0195a6b4-6f15-7d4b-b2c1-05b2a3d6e7f8')], type: 'object')],
                    type: 'object',
                ),
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
            throw new \LogicException('Audit log resource cannot be created for an entity without id.');
        }

        return new ResourceItem(
            type: 'audit_logs',
            id: $id,
            attributes: [
                'action' => $auditLog->getAction()->value,
                'message' => $auditLog->getMessage(),
                'attribute_changes' => $auditLog->getAttributeChanges(),
                'metadata' => $auditLog->getMetadata(),
                'created_at' => $auditLog->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            relationships: [
                'user' => ['data' => self::userIdentifier($auditLog)],
                'entity' => ['data' => [
                    'type' => $auditLog->getEntityType()->resourceType(),
                    'id' => $auditLog->getEntityId(),
                ]],
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

    /**
     * @return array{type: string, id: string}|null
     */
    private static function userIdentifier(AuditLog $auditLog): ?array
    {
        $userId = $auditLog->getUser()?->getId();

        return null === $userId ? null : ['type' => UserResource::TYPE, 'id' => (string) $userId];
    }
}
