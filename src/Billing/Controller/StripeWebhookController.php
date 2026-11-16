<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Message\RefreshSubscription;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The event is only a signal: the worker fetches the subscription from Stripe (ADR 0037).
 */
#[WithMonologChannel('billing')]
final readonly class StripeWebhookController
{
    private const array SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function __construct(
        #[Target('billing.bus')]
        private MessageBusInterface $billingBus,
        private LoggerInterface $logger,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private string $webhookSecret,
    ) {
    }

    #[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function receive(Request $request): Response
    {
        // Our configuration, not the sender, is at fault: 500 keeps Stripe retrying until the secret is set.
        if ('' === $this->webhookSecret) {
            $this->logger->critical('Stripe webhook not checked: STRIPE_WEBHOOK_SECRET is not set');

            return new Response(status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $signature = $request->headers->get('Stripe-Signature');
        if (null === $signature) {
            $this->logRefusal($request, reason: 'no_header', signedAt: null);

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $this->webhookSecret, Webhook::DEFAULT_TOLERANCE);
        } catch (SignatureVerificationException $refusal) {
            // stripe-php has no exception type or code for an old timestamp, only this message.
            $reason = 'Timestamp outside the tolerance zone' === $refusal->getMessage() ? 'outside_tolerance' : 'no_match';

            $this->logRefusal($request, $reason, $this->signedAt($signature));

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        $subscriptionId = $this->findSubscriptionIdToRefresh($event);
        if (null !== $subscriptionId) {
            $this->billingBus->dispatch(new RefreshSubscription($subscriptionId));
        }

        return new Response(status: Response::HTTP_OK);
    }

    private function findSubscriptionIdToRefresh(Event $event): ?string
    {
        $object = $event->data->object;

        if ('checkout.session.completed' === $event->type) {
            // A session in payment mode, never opened by our Checkout, names no subscription.
            $subscriptionId = $object['subscription'];

            return is_string($subscriptionId) ? $subscriptionId : null;
        }

        if (in_array($event->type, self::SUBSCRIPTION_EVENTS, true)) {
            $subscriptionId = $object->id;

            return is_string($subscriptionId) ? $subscriptionId : null;
        }

        return null;
    }

    /** Never the body or the headers: the body may be a customer's data, or a stranger's choice. */
    private function logRefusal(Request $request, string $reason, ?int $signedAt): void
    {
        $this->logger->warning('Stripe webhook refused: its signature does not verify', [
            'reason' => $reason,
            'signed_at' => $signedAt,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'body_bytes' => strlen($request->getContent()),
        ]);
    }

    private function signedAt(string $signature): ?int
    {
        foreach (explode(',', $signature) as $item) {
            [$key, $value] = array_pad(explode('=', $item, 2), 2, '');
            if ('t' === $key && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
