<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Command\SyncSubscription;
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

#[WithMonologChannel('billing')]
final readonly class StripeWebhookController
{
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
        if ('' === $this->webhookSecret) {
            $this->logger->critical('Stripe webhook not checked: STRIPE_WEBHOOK_SECRET is not set');

            return new Response(status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $signature = $request->headers->get('Stripe-Signature');
        if (null === $signature) {
            $this->logRefusal($request, reason: 'no_header');

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $this->webhookSecret, Webhook::DEFAULT_TOLERANCE);
        } catch (SignatureVerificationException $refusal) {
            // stripe-php has no exception type or code for an old timestamp, only this message.
            $reason = 'Timestamp outside the tolerance zone' === $refusal->getMessage() ? 'outside_tolerance' : 'no_match';
            $this->logRefusal($request, $reason);

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        $subscriptionId = $this->findSubscriptionIdToSync($event);
        if (null !== $subscriptionId) {
            $this->billingBus->dispatch(new SyncSubscription($subscriptionId));
        }

        return new Response(status: Response::HTTP_OK);
    }

    private function findSubscriptionIdToSync(Event $event): ?string
    {
        $object = $event->data->object;

        $subscriptionId = match ($event->type) {
            // Null for a session in payment mode, which our Checkout never opens.
            Event::CHECKOUT_SESSION_COMPLETED => $object['subscription'],
            Event::CUSTOMER_SUBSCRIPTION_CREATED,
            Event::CUSTOMER_SUBSCRIPTION_UPDATED,
            Event::CUSTOMER_SUBSCRIPTION_DELETED => $object->id,
            default => null,
        };

        return is_string($subscriptionId) ? $subscriptionId : null;
    }

    private function logRefusal(Request $request, string $reason): void
    {
        $this->logger->warning('Stripe webhook refused: its signature does not verify', [
            'reason' => $reason,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'body_bytes' => strlen($request->getContent()),
        ]);
    }
}
