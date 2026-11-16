<?php

declare(strict_types=1);

namespace App\Billing\Message;

final readonly class RefreshSubscription
{
    public function __construct(
        public string $stripeSubscriptionId,
    ) {
    }
}
