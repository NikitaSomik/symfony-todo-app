<?php

declare(strict_types=1);

namespace App\Workspace\DTO;

use App\Workspace\Enum\WorkspaceRole;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['email'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'colleague@example.com'),
        new OA\Property(property: 'role', ref: new Model(type: WorkspaceRole::class)),
    ]
)]
readonly class AddMemberDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        #[Assert\Choice(callback: [WorkspaceRole::class, 'values'])]
        public string $role = WorkspaceRole::MEMBER->value,
    ) {
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->role);
    }
}
