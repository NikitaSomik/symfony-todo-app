<?php

declare(strict_types=1);

namespace App\Billing\Checkout;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use Symfony\Component\Uid\Uuid;

interface CheckoutPages
{
    /**
     * @throws PaymentProviderUnavailableException
     */
    public function open(Uuid $workspaceId, Plan $plan, string $buyerEmail): CheckoutPage;
}
