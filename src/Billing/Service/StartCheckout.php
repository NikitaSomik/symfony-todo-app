<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Auth\Contract\UserDirectory;
use App\Billing\Enum\Plan;
use App\Billing\Exception\CannotBuyPlanException;
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
        private UserDirectory $users,
    ) {
    }

    public function handle(Uuid $workspaceId, Plan $plan, int $buyerId): CheckoutPage
    {
        if (Plan::FREE !== $this->subscriptions->planOf($workspaceId)) {
            throw CannotBuyPlanException::alreadySubscribed();
        }

        $customerId = $this->customerOf($workspaceId, $buyerId);

        // Our copy of the plan follows the webhook; Stripe knows of a payment before it arrives.
        if ($this->payments->hasSubscription($customerId)) {
            throw CannotBuyPlanException::alreadySubscribed();
        }

        return $this->payments->openCheckout($customerId, $workspaceId, $plan);
    }

    private function customerOf(Uuid $workspaceId, int $buyerId): string
    {
        $customerId = $this->payers->findCustomerId($workspaceId);

        if (null !== $customerId) {
            return $customerId;
        }

        $buyerEmail = $this->users->findEmailById($buyerId) ?? throw new \LogicException(sprintf('The user %d who is buying does not exist.', $buyerId));

        return $this->payers->saveIfAbsent($workspaceId, $this->payments->createCustomer($workspaceId, $buyerEmail));
    }
}
