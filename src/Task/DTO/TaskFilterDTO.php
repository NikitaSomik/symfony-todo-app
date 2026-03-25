<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\Enum\TaskStatus;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), nullable: true),
    ]
)]
readonly class TaskFilterDTO
{
    public function __construct(
        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public ?string $status = null,
    ) {
    }
}
