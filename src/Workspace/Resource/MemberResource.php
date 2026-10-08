<?php

declare(strict_types=1);

namespace App\Workspace\Resource;

use App\Shared\Api\ResourceItem;
use App\Workspace\Entity\Membership;
use App\Workspace\Enum\WorkspaceRole;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        new OA\Property(
            property: 'links',
            properties: [
                new OA\Property(property: 'self', type: 'string', example: '/api/v1/workspaces/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b/members/7'),
            ],
            type: 'object',
        ),
    ]
)]
final readonly class MemberResource
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function toItem(Membership $membership): ResourceItem
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
            links: ['self' => $this->selfUrl($membership)],
        );
    }

    /**
     * @param Membership[] $memberships
     *
     * @return ResourceItem[]
     */
    public function toItems(array $memberships): array
    {
        return array_map($this->toItem(...), $memberships);
    }

    public function selfUrl(Membership $membership): string
    {
        return $this->urls->generate('api_workspace_member_get', [
            'id' => $membership->getWorkspace()->getId()->toRfc4122(),
            'userId' => $membership->getUserId(),
        ]);
    }
}
