<?php

declare(strict_types=1);

namespace App\Workspace\DTO;

use App\Workspace\Entity\Workspace;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: Workspace::NAME_MAX_LENGTH, example: 'Mobile team'),
    ]
)]
readonly class WorkspaceDTO
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Workspace::NAME_MAX_LENGTH)]
        public string $name,
    ) {
    }
}
