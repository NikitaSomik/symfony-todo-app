<?php

declare(strict_types=1);

namespace App\Billing\Command;

final readonly class SyncSubscription
{
    public function __construct(
        public string $stripeSubscriptionId,
    ) {
    }
}
