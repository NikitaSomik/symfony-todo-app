<?php

declare(strict_types=1);

namespace App\Billing\Query;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;

final readonly class WorkspaceBilling
{
    public function __construct(
        public Plan $plan,
        public ?SubscriptionStatus $status,
    ) {
    }
}
