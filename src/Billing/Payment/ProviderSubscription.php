<?php

declare(strict_types=1);

namespace App\Billing\Payment;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Symfony\Component\Uid\Uuid;

/** A subscription as the payment provider answers it now. */
final readonly class ProviderSubscription
{
    public function __construct(
        public string $id,
        /** Null when the subscription names no workspace, such as one created by hand in the provider's dashboard. */
        public ?Uuid $workspaceId,
        public Plan $plan,
        public SubscriptionStatus $status,
    ) {
    }
}
