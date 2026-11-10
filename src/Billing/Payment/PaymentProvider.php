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
    public function createCustomer(Uuid $workspaceId, string $email): string;

    public function closeOpenCheckoutPages(string $customerId): void;

    public function hasSubscription(string $customerId): bool;

    public function openCheckout(string $customerId, Uuid $workspaceId, Plan $plan): CheckoutPage;
}
