<?php

declare(strict_types=1);

namespace App\Task\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['from', 'to'],
    properties: [
        new OA\Property(property: 'from', description: 'Id of the user whose unfinished tasks are handed over', type: 'integer', example: 5),
        new OA\Property(property: 'to', description: 'Id of the owner or member who takes them', type: 'integer', example: 7),
    ]
)]
readonly class ReassignTasksDTO
{
    public function __construct(
        #[Assert\Positive]
        public int $from,

        #[Assert\Positive]
        #[Assert\NotEqualTo(propertyPath: 'from')]
        public int $to,
    ) {
    }
}
