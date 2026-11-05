<?php

declare(strict_types=1);

namespace App\Billing\DTO;

use App\Billing\Enum\Plan;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['plan'], properties: [new OA\Property(property: 'plan', type: 'string', enum: ['team', 'business'], example: 'team')])]
readonly class CheckoutDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['team', 'business'])]
        public string $plan,
    ) {
    }

    public function plan(): Plan
    {
        return Plan::from($this->plan);
    }
}
