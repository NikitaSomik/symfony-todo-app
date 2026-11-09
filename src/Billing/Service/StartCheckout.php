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

        $customerId = $this->payers->findCustomerId($workspaceId);

        if (null === $customerId) {
            $createdId = $this->payments->createCustomer($workspaceId, $buyerEmail);
            $customerId = $this->payers->saveOrGetExisting($workspaceId, $createdId);

            // Made a moment ago, so Stripe has no subscription of it; a customer a parallel purchase saved first may have one.
            if ($customerId === $createdId) {
                return $this->payments->openCheckout($customerId, $workspaceId, $plan);
            }
        }

        // Our copy of the plan follows the webhook; Stripe knows of a payment before it arrives.
        if ($this->payments->hasSubscription($customerId)) {
            throw new AlreadySubscribedException();
        }

        return $this->payments->openCheckout($customerId, $workspaceId, $plan);
    }
}
