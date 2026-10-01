<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\ValueObject\CancellationReason;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['reason'],
    properties: [
        new OA\Property(property: 'reason', type: 'string', maxLength: CancellationReason::MAX_LENGTH, minLength: CancellationReason::MIN_LENGTH, example: 'Task is no longer relevant'),
    ]
)]
readonly class CancelTaskDTO
{
    public function __construct(
        // Trimmed like the value object trims it, so what passes here is what CancellationReason accepts.
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: CancellationReason::MIN_LENGTH, max: CancellationReason::MAX_LENGTH, normalizer: 'trim')]
        public string $reason,
    ) {
    }
}
