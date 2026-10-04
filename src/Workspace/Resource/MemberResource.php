<?php

declare(strict_types=1);

namespace App\Workspace\Resource;

use App\Shared\Api\ResourceItem;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Membership;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'workspace_members'),
        new OA\Property(property: 'id', type: 'string', example: '12'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'role', ref: new Model(type: WorkspaceRole::class)),
                new OA\Property(property: 'joined_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'relationships',
            properties: [
                new OA\Property(
                    property: 'user',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: 'users'), new OA\Property(property: 'id', type: 'string', example: '7')], type: 'object')],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'workspace',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: 'workspaces'), new OA\Property(property: 'id', type: 'string', format: 'uuid')], type: 'object')],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    ]
)]
final class MemberResource
{
    public static function toItem(Membership $membership): ResourceItem
    {
        return new ResourceItem(
            type: 'workspace_members',
            id: $membership->getId() ?? throw new \LogicException('Member resource cannot be created for a membership without id.'),
            attributes: [
                'role' => $membership->getRole()->value,
                'joined_at' => $membership->getJoinedAt()->format(\DateTimeInterface::ATOM),
            ],
            relationships: [
                'user' => ['data' => ['type' => 'users', 'id' => (string) $membership->getUserId()]],
                'workspace' => ['data' => ['type' => 'workspaces', 'id' => $membership->getWorkspace()->getId()->toRfc4122()]],
            ],
        );
    }

    /**
     * @param Membership[] $memberships
     *
     * @return ResourceItem[]
     */
    public static function toItems(array $memberships): array
    {
        return array_map(self::toItem(...), $memberships);
    }
}
