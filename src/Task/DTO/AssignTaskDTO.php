<?php

declare(strict_types=1);

namespace App\Task\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['user_id'],
    properties: [
        new OA\Property(property: 'user_id', type: 'integer', example: 7),
    ]
)]
readonly class AssignTaskDTO
{
    public function __construct(
        #[Assert\Positive]
        public int $user_id,
    ) {
    }
}
