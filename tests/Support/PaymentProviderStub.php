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

    /** @var list<string> */
    public array $checkedCustomers = [];

    /** @var list<array{customerId: string, workspaceId: Uuid, plan: Plan}> */
    public array $opened = [];

    /** @var list<string> */
    public array $closedPagesOf = [];

    /** @var list<string> customers whose open page gets paid while it is being closed */
    public array $paidWhileClosing = [];

    /** @var list<string> the provider's methods in the order they were called */
    public array $calls = [];

    public ?\Throwable $failure = null;

    public ?\Throwable $closingFailure = null;

    public function createCustomer(Uuid $workspaceId, string $email): string
    {
        $this->failIfAsked();
        $this->calls[] = 'createCustomer';
        $this->createdCustomers[] = ['workspaceId' => $workspaceId, 'email' => $email];

        return 'cus_stub_'.count($this->createdCustomers);
    }

    public function hasSubscription(string $customerId): bool
    {
        $this->failIfAsked();
        $this->calls[] = 'hasSubscription';
        $this->checkedCustomers[] = $customerId;

        return in_array($customerId, $this->subscribedCustomers, true);
    }

    public function openCheckout(string $customerId, Uuid $workspaceId, Plan $plan): CheckoutPage
    {
        $this->failIfAsked();
        $this->calls[] = 'openCheckout';
        $this->opened[] = ['customerId' => $customerId, 'workspaceId' => $workspaceId, 'plan' => $plan];

        return new CheckoutPage('cs_test_stub', 'https://checkout.stripe.test/c/pay/cs_test_stub');
    }

    public function closeOpenCheckoutPages(string $customerId): void
    {
        $this->failIfAsked();
        $this->calls[] = 'closeOpenCheckoutPages';

        if (null !== $this->closingFailure) {
            throw $this->closingFailure;
        }

        $this->closedPagesOf[] = $customerId;

        if (in_array($customerId, $this->paidWhileClosing, true)) {
            $this->subscribedCustomers[] = $customerId;
        }
    }

    private function failIfAsked(): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}
