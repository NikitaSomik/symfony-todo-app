<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Payment\CheckoutPage;
use App\Billing\Payment\PaymentProvider;
use Monolog\Attribute\WithMonologChannel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[WithMonologChannel('billing')]
final readonly class StripePaymentProvider implements PaymentProvider
{
    /** The shortest Stripe allows is 30 minutes; its default, 24 hours, leaves a forgotten page payable for a day. */
    private const int PAGE_LIFETIME_SECONDS = 60 * 60;

    /** A price changed in the Dashboard reaches new pages within this time. */
    private const int PRICE_CACHE_SECONDS = 60 * 60;

    private const array FINISHED_STATUSES = ['canceled', 'incomplete_expired'];

    public function __construct(
        private StripeClient $stripe,
        private ClockInterface $clock,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire(env: 'BILLING_RETURN_URL')]
        private string $returnUrl,
    ) {
    }

    public function createCustomer(Uuid $workspaceId, string $email): string
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $email,
                'metadata' => ['workspace_id' => $workspaceId->toRfc4122()],
            ]);
        } catch (ApiErrorException $exception) {
            throw $this->translated($exception);
        }

        $this->logger->info('Stripe customer created', [
            'workspace_id' => $workspaceId->toRfc4122(),
            'customer_id' => $customer->id,
            'stripe_request_id' => self::requestIdOf($customer),
        ]);

        return $customer->id;
    }

    public function hasSubscription(string $customerId): bool
    {
        try {
            $subscriptions = $this->stripe->subscriptions->all(['customer' => $customerId, 'status' => 'all', 'limit' => 100]);
        } catch (ApiErrorException $exception) {
            throw $this->translated($exception);
        }

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

        try {
            $session = $this->stripe->checkout->sessions->create([
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
            ]);
        } catch (ApiErrorException $exception) {
            throw $this->translated($exception);
        }

        $this->logger->info('Stripe checkout page opened', [
            'workspace_id' => $workspaceId->toRfc4122(),
            'customer_id' => $customerId,
            'session_id' => $session->id,
            'plan' => $plan->value,
            'stripe_request_id' => self::requestIdOf($session),
        ]);

        return new CheckoutPage(
            id: $session->id,
            url: $session->url ?? throw new \LogicException(sprintf('Stripe opened the checkout page %s without an address.', $session->id)),
        );
    }

    /** Prices live in Stripe; a price is found by the lookup key it shares with the plan in every account. */
    private function priceOf(Plan $plan): string
    {
        return $this->cache->get('billing.stripe_price.'.$plan->value, function (ItemInterface $item) use ($plan): string {
            $item->expiresAfter(self::PRICE_CACHE_SECONDS);

            return $this->findPrice($plan);
        });
    }

    private function findPrice(Plan $plan): string
    {
        try {
            $prices = $this->stripe->prices->all(['lookup_keys' => [$plan->value], 'active' => true, 'limit' => 1]);
        } catch (ApiErrorException $exception) {
            throw $this->translated($exception);
        }

        if ([] === $prices->data) {
            throw new \LogicException(sprintf('Stripe has no active price with the lookup key "%s".', $plan->value));
        }

        return $prices->data[0]->id;
    }

    /** Stripe being down becomes the port's exception; any other refusal is our fault and stays a 500. */
    private function translated(ApiErrorException $exception): \Throwable
    {
        $context = [
            'stripe_request_id' => $exception->getRequestId(),
            'http_status' => $exception->getHttpStatus(),
            'stripe_code' => $exception->getStripeCode(),
            'exception' => $exception,
        ];

        if ($exception instanceof ApiConnectionException || $exception instanceof RateLimitException || $exception->getHttpStatus() >= 500) {
            $this->logger->warning('Stripe request failed', $context);

            return PaymentProviderUnavailableException::because($exception);
        }

        $this->logger->error('Stripe request failed', $context);

        return $exception;
    }

    private static function requestIdOf(StripeObject $object): ?string
    {
        $response = $object->getLastResponse();

        if (null === $response || !isset($response->headers['request-id'])) {
            return null;
        }

        $requestId = $response->headers['request-id'];

        return is_string($requestId) ? $requestId : null;
    }
}
