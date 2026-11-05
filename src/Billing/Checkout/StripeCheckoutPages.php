<?php

declare(strict_types=1);

namespace App\Billing\Checkout;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use Psr\Clock\ClockInterface;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class StripeCheckoutPages implements CheckoutPages
{
    /**
     * Asking again within this window gives back the same page, so a double click or a
     * second tab cannot become a second payment.
     */
    private const int REUSE_WINDOW_SECONDS = 30 * 60;

    /** Stripe takes an expiry between 30 minutes and 24 hours ahead; a page lives one window past its own. */
    private const int PAGE_LIFETIME_SECONDS = 2 * self::REUSE_WINDOW_SECONDS;

    public function __construct(
        private StripeClient $stripe,
        private ClockInterface $clock,
        #[Autowire(env: 'BILLING_RETURN_URL')]
        private string $returnUrl,
    ) {
    }

    public function open(Uuid $workspaceId, Plan $plan, string $buyerEmail): CheckoutPage
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - $now % self::REUSE_WINDOW_SECONDS;

        // Stripe answers a repeated key with the first page only if every parameter is the same,
        // so the key names everything that varies, and the expiry is counted from the window.
        $idempotencyKey = sprintf('checkout-%s-%s-%s-%d', $workspaceId->toRfc4122(), $plan->value, hash('xxh128', $buyerEmail), $windowStart);

        try {
            $session = $this->stripe->checkout->sessions->create(
                [
                    'mode' => 'subscription',
                    'line_items' => [['price' => $this->priceOf($plan), 'quantity' => 1]],
                    'customer_email' => $buyerEmail,
                    'client_reference_id' => $workspaceId->toRfc4122(),
                    // On the subscription too, so a webhook that comes before the session's own still finds its workspace.
                    'subscription_data' => ['metadata' => ['workspace_id' => $workspaceId->toRfc4122()]],
                    'metadata' => ['workspace_id' => $workspaceId->toRfc4122()],
                    'expires_at' => $windowStart + self::PAGE_LIFETIME_SECONDS,
                    'success_url' => $this->returnUrl.'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $this->returnUrl.'?checkout=cancelled',
                ],
                ['idempotency_key' => $idempotencyKey],
            );
        } catch (ApiConnectionException|RateLimitException $exception) {
            throw PaymentProviderUnavailableException::because($exception);
        } catch (ApiErrorException $exception) {
            // A 4xx other than the rate limit is a request Stripe will never accept: our fault, so it stays a 500.
            if ($exception->getHttpStatus() >= 500) {
                throw PaymentProviderUnavailableException::because($exception);
            }

            throw $exception;
        }

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
