<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Billing\Enum\Plan;
use App\Billing\Payment\CheckoutPage;
use App\Billing\Payment\PaymentProvider;
use Symfony\Component\Uid\Uuid;

/** Stands in for Stripe: customers and pages that exist nowhere, and a record of what was asked. */
final class PaymentProviderStub implements PaymentProvider
{
    /** @var list<array{workspaceId: Uuid, email: string}> */
    public array $createdCustomers = [];

    /** @var list<string> */
    public array $subscribedCustomers = [];

    /** @var list<array{customerId: string, workspaceId: Uuid, plan: Plan}> */
    public array $opened = [];

    public ?\Throwable $failure = null;

    public function createCustomer(Uuid $workspaceId, string $email): string
    {
        $this->failIfAsked();
        $this->createdCustomers[] = ['workspaceId' => $workspaceId, 'email' => $email];

        return 'cus_stub_'.count($this->createdCustomers);
    }

    public function hasSubscription(string $customerId): bool
    {
        $this->failIfAsked();

        return in_array($customerId, $this->subscribedCustomers, true);
    }

    public function openCheckout(string $customerId, Uuid $workspaceId, Plan $plan): CheckoutPage
    {
        $this->failIfAsked();
        $this->opened[] = ['customerId' => $customerId, 'workspaceId' => $workspaceId, 'plan' => $plan];

        return new CheckoutPage('cs_test_stub', 'https://checkout.stripe.test/c/pay/cs_test_stub');
    }

    private function failIfAsked(): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}
