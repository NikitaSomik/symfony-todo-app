<?php

declare(strict_types=1);

namespace App\Billing\DTO;

use App\Billing\Enum\Plan;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CheckoutDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: 'paidPlans')]
        public string $plan,
    ) {
    }

    /** @return list<string> */
    public static function paidPlans(): array
    {
        return array_map(static fn (Plan $plan): string => $plan->value, Plan::paid());
    }

    public function plan(): Plan
    {
        return Plan::from($this->plan);
    }
}
