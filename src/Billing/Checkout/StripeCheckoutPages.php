<?php

declare(strict_types=1);

namespace App\Billing\Checkout;

use App\Billing\Enum\Plan;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class StripeCheckoutPages implements CheckoutPages
{
    public function __construct(
        private StripeClient $stripe,
        #[Autowire(env: 'BILLING_RETURN_URL')]
        private string $returnUrl,
    ) {
    }

    public function open(Uuid $workspaceId, Plan $plan): CheckoutPage
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [['price' => $this->priceOf($plan), 'quantity' => 1]],
            'client_reference_id' => $workspaceId->toRfc4122(),
            // On the subscription too, so a webhook that comes before the session's own still finds its workspace.
            'subscription_data' => ['metadata' => ['workspace_id' => $workspaceId->toRfc4122()]],
            'metadata' => ['workspace_id' => $workspaceId->toRfc4122()],
            'success_url' => $this->returnUrl.'?checkout=success',
            'cancel_url' => $this->returnUrl.'?checkout=cancelled',
        ]);

        return new CheckoutPage($session->id, (string) $session->url);
    }

    /** Prices live in Stripe; a price is found by the lookup key it shares with the plan in every account. */
    private function priceOf(Plan $plan): string
    {
        $prices = $this->stripe->prices->all(['lookup_keys' => [$plan->value], 'active' => true, 'limit' => 1]);

        if ([] === $prices->data) {
            throw new \LogicException(sprintf('Stripe has no active price with the lookup key "%s".', $plan->value));
        }

        return $prices->data[0]->id;
    }
}
