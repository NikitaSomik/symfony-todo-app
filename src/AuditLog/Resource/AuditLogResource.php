<?php

declare(strict_types=1);

namespace App\AuditLog\Resource;

use App\AuditLog\Entity\AuditLog;
use App\AuditLog\Enum\AuditLogAction;
use App\Shared\Api\ResourceItem;
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
                new OA\Property(property: 'metadata', type: 'object', example: ['entity_data' => ['title' => 'Buy milk', 'status' => 'todo']], nullable: true, additionalProperties: new OA\AdditionalProperties()),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'relationships',
            properties: [
                new OA\Property(
                    property: 'user',
                    description: 'Who made the change; null for an action no user made. The id stays when the user no longer exists',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: self::ACTOR_TYPE), new OA\Property(property: 'id', type: 'string', example: '7')], type: 'object', nullable: true)],
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
    /** The JSON:API type users are exposed under; the audit log names the actor without depending on the module that owns users. */
    private const string ACTOR_TYPE = 'users';

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
        $actorId = $auditLog->getActorId();

        return null === $actorId ? null : ['type' => self::ACTOR_TYPE, 'id' => (string) $actorId];
    }
}
