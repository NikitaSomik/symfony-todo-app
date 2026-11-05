<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Auth\Contract\UserDirectory;
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
        private UserDirectory $users,
    ) {
    }

    public function handle(Uuid $workspaceId, Plan $plan, int $buyerId): CheckoutPage
    {
        $currentPlan = $this->subscriptions->planOf($workspaceId);

        if (Plan::FREE !== $currentPlan) {
            throw CannotBuyPlanException::alreadySubscribed($currentPlan);
        }

        $buyerEmail = $this->users->findEmailById($buyerId) ?? throw new \LogicException(sprintf('The user %d who is buying does not exist.', $buyerId));

        return $this->checkoutPages->open($workspaceId, $plan, $buyerEmail);
    }
}
