<?php

declare(strict_types=1);

namespace App\Billing\Payment;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use Symfony\Component\Uid\Uuid;

/**
 * @throws PaymentProviderUnavailableException from every method
 */
interface PaymentProvider
{
    /** @return string the id of the new customer */
    public function createCustomer(Uuid $workspaceId, string $email): string;

    /** Whether the customer has a subscription that is not over yet, paid or not. */
    public function hasSubscription(string $customerId): bool;

    public function openCheckout(string $customerId, Uuid $workspaceId, Plan $plan): CheckoutPage;
}
