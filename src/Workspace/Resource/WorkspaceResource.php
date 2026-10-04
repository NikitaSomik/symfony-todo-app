<?php

declare(strict_types=1);

namespace App\Workspace\Resource;

use App\Shared\Api\ResourceItem;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'workspaces'),
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Mobile team'),
                new OA\Property(property: 'role', ref: new Model(type: WorkspaceRole::class), description: 'The role of the user who asks'),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'links',
            properties: [
                new OA\Property(property: 'self', type: 'string', example: '/api/v1/workspaces/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b'),
                new OA\Property(property: 'members', type: 'string', example: '/api/v1/workspaces/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b/members'),
            ],
            type: 'object',
        ),
    ]
)]
final readonly class WorkspaceResource
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function toItem(Workspace $workspace, int $viewerId): ResourceItem
    {
        $id = ['id' => $workspace->getId()->toRfc4122()];

        return new ResourceItem(
            type: 'workspaces',
            id: $workspace->getId()->toRfc4122(),
            attributes: [
                'name' => $workspace->getName(),
                'role' => $workspace->roleOf($viewerId)?->value,
                'created_at' => $workspace->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            links: [
                'self' => $this->urls->generate('api_workspace_get', $id),
                'members' => $this->urls->generate('api_workspace_member_get_all', $id),
            ],
        );
    }

    /**
     * @param Workspace[] $workspaces
     *
     * @return ResourceItem[]
     */
    public function toItems(array $workspaces, int $viewerId): array
    {
        return array_map(fn (Workspace $workspace): ResourceItem => $this->toItem($workspace, $viewerId), $workspaces);
    }
}
