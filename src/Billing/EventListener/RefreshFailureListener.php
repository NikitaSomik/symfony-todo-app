<?php

declare(strict_types=1);

namespace App\Billing\EventListener;

use App\Billing\Message\RefreshSubscription;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/** Until the nightly reconciliation exists, this error is the only trace of a customer who paid and did not get the plan. */
#[AsEventListener]
#[WithMonologChannel('billing')]
final readonly class RefreshFailureListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof RefreshSubscription || $event->willRetry()) {
            return;
        }

        $this->logger->error('Stripe subscription not refreshed after its retries', [
            'subscription_id' => $message->stripeSubscriptionId,
            'exception' => $event->getThrowable(),
        ]);
    }
}
