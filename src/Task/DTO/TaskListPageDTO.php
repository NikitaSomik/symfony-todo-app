<?php

declare(strict_types=1);

namespace App\Task\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'number', type: 'integer', example: 1, minimum: 1, default: 1),
        new OA\Property(property: 'size', type: 'integer', example: 20, minimum: 1, maximum: 100, default: 20),
    ]
)]
readonly class TaskListPageDTO
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        public int $number = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $size = 20,
    ) {
    }
}
