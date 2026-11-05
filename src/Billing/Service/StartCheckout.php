<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Checkout\CheckoutPage;
use App\Billing\Checkout\CheckoutPages;
use App\Billing\Enum\Plan;
use App\Billing\Exception\CannotBuyPlanException;
use App\Billing\Repository\SubscriptionRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Opens a payment page and changes nothing here: the plan follows Stripe's webhook, not
 * this call, so a page left unpaid leaves no trace (ADR 0031).
 */
final readonly class StartCheckout
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private CheckoutPages $checkoutPages,
    ) {
    }

    public function handle(Uuid $workspaceId, Plan $plan): CheckoutPage
    {
        if (Plan::FREE !== $this->subscriptions->planOf($workspaceId)) {
            throw CannotBuyPlanException::alreadySubscribed();
        }

        return $this->checkoutPages->open($workspaceId, $plan);
    }
}
