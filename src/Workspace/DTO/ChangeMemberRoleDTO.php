<?php

declare(strict_types=1);

namespace App\Workspace\DTO;

use App\Workspace\Enum\WorkspaceRole;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['role'],
    properties: [
        new OA\Property(property: 'role', ref: new Model(type: WorkspaceRole::class)),
    ]
)]
readonly class ChangeMemberRoleDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [WorkspaceRole::class, 'values'])]
        public string $role,
    ) {
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->role);
    }
}
