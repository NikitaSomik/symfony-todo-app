<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\ValueObject\BlockReason;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['reason'],
    properties: [
        new OA\Property(property: 'reason', type: 'string', maxLength: BlockReason::MAX_LENGTH, minLength: BlockReason::MIN_LENGTH, example: 'Waiting for access from the client'),
    ]
)]
readonly class BlockTaskDTO
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: BlockReason::MIN_LENGTH, max: BlockReason::MAX_LENGTH, normalizer: 'trim')]
        public string $reason,
    ) {
    }

    public function reason(): BlockReason
    {
        return new BlockReason($this->reason);
    }
}
