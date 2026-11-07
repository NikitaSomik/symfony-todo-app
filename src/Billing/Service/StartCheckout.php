<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\Plan;
use App\Billing\Exception\AlreadySubscribedException;
use App\Billing\Payment\CheckoutPage;
use App\Billing\Payment\PaymentProvider;
use App\Billing\Repository\PayerRepository;
use App\Billing\Repository\SubscriptionRepository;
use Symfony\Component\Uid\Uuid;

final readonly class StartCheckout
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PayerRepository $payers,
        private PaymentProvider $payments,
    ) {
    }

    public function handle(Uuid $workspaceId, Plan $plan, string $buyerEmail): CheckoutPage
    {
        if (Plan::FREE !== $this->subscriptions->planOf($workspaceId)) {
            throw new AlreadySubscribedException();
        }

        $customerId = $this->customerOf($workspaceId, $buyerEmail);

        // Our copy of the plan follows the webhook; Stripe knows of a payment before it arrives.
        if ($this->payments->hasSubscription($customerId)) {
            throw new AlreadySubscribedException();
        }

        return $this->payments->openCheckout($customerId, $workspaceId, $plan);
    }

    private function customerOf(Uuid $workspaceId, string $buyerEmail): string
    {
        $customerId = $this->payers->findCustomerId($workspaceId);

        if (null !== $customerId) {
            return $customerId;
        }

        return $this->payers->saveOrGetExisting($workspaceId, $this->payments->createCustomer($workspaceId, $buyerEmail));
    }
}
