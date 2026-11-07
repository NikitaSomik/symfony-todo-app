<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Payment\CheckoutPage;
use App\Billing\Payment\PaymentProvider;
use Psr\Clock\ClockInterface;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class StripePaymentProvider implements PaymentProvider
{
    /** The shortest Stripe allows is 30 minutes; its default, 24 hours, leaves a forgotten page payable for a day. */
    private const int PAGE_LIFETIME_SECONDS = 60 * 60;

    private const array FINISHED_STATUSES = ['canceled', 'incomplete_expired'];

    public function __construct(
        private StripeClient $stripe,
        private ClockInterface $clock,
        #[Autowire(env: 'BILLING_RETURN_URL')]
        private string $returnUrl,
    ) {
    }

    public function createCustomer(Uuid $workspaceId, string $email): string
    {
        return $this->ask(fn (): string => $this->stripe->customers->create([
            'email' => $email,
            'metadata' => ['workspace_id' => $workspaceId->toRfc4122()],
        ])->id);
    }

    public function hasSubscription(string $customerId): bool
    {
        $subscriptions = $this->ask(fn () => $this->stripe->subscriptions->all(['customer' => $customerId, 'status' => 'all', 'limit' => 100]));

        foreach ($subscriptions->data as $subscription) {
            if (!in_array($subscription->status, self::FINISHED_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    public function openCheckout(string $customerId, Uuid $workspaceId, Plan $plan): CheckoutPage
    {
        $price = $this->priceOf($plan);

        $session = $this->ask(fn () => $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'client_reference_id' => $workspaceId->toRfc4122(),
            // On the subscription too, so a webhook that comes before the session's own still finds its workspace.
            'subscription_data' => ['metadata' => ['workspace_id' => $workspaceId->toRfc4122()]],
            'metadata' => ['workspace_id' => $workspaceId->toRfc4122()],
            'expires_at' => $this->clock->now()->getTimestamp() + self::PAGE_LIFETIME_SECONDS,
            'success_url' => $this->returnUrl.'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->returnUrl.'?checkout=cancelled',
        ]));

        return new CheckoutPage($session->id, (string) $session->url);
    }

    /** Prices live in Stripe; a price is found by the lookup key it shares with the plan in every account. */
    private function priceOf(Plan $plan): string
    {
        $prices = $this->ask(fn () => $this->stripe->prices->all(['lookup_keys' => [$plan->value], 'active' => true, 'limit' => 1]));

        if ([] === $prices->data) {
            throw new \LogicException(sprintf('Stripe has no active price with the lookup key "%s".', $plan->value));
        }

        return $prices->data[0]->id;
    }

    /**
     * @template T
     *
     * @param \Closure(): T $request
     *
     * @return T
     */
    private function ask(\Closure $request): mixed
    {
        try {
            return $request();
        } catch (ApiConnectionException|RateLimitException $exception) {
            throw PaymentProviderUnavailableException::because($exception);
        } catch (ApiErrorException $exception) {
            // A 4xx other than the rate limit is a request Stripe will never accept: our fault, so it stays a 500.
            if ($exception->getHttpStatus() >= 500) {
                throw PaymentProviderUnavailableException::because($exception);
            }

            throw $exception;
        }
    }
}
