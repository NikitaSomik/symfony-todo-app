<?php

declare(strict_types=1);

namespace App\Billing\Checkout;

use App\Billing\Enum\Plan;
use Symfony\Component\Uid\Uuid;

interface CheckoutPages
{
    public function open(Uuid $workspaceId, Plan $plan): CheckoutPage;
}
