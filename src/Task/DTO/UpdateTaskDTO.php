<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\Enum\TaskStatus;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
        new OA\Property(
            property: 'description',
            type: 'string',
            example: '2 liters',
            nullable: true,
            minLength: 3,
            maxLength: 2000,
        ),
        new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), description: 'Task status'),
    ]
)]
readonly class UpdateTaskDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\Length(min: 3, max: 2000)]
        public ?string $description = null,

        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public string $status = TaskStatus::TODO->value,
    ) {
    }
}
