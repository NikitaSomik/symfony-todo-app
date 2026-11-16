<?php

declare(strict_types=1);

namespace App\Billing\Payment;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Symfony\Component\Uid\Uuid;

final readonly class ProviderSubscription
{
    public function __construct(
        public string $id,
        /** Null when the subscription was not created by our Checkout, such as one from `stripe trigger` in test mode. */
        public ?Uuid $workspaceId,
        public Plan $plan,
        public SubscriptionStatus $status,
    ) {
    }
}
