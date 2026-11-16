<?php

declare(strict_types=1);

namespace App\Billing\Payment;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;

final readonly class ProviderSubscription
{
    public function __construct(
        public string $id,
        public string $customerId,
        public Plan $plan,
        public SubscriptionStatus $status,
    ) {
    }
}
