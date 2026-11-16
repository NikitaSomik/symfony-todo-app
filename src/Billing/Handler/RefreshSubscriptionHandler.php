<?php

declare(strict_types=1);

namespace App\Billing\Handler;

use App\Billing\Entity\Subscription;
use App\Billing\Message\RefreshSubscription;
use App\Billing\Payment\PaymentProvider;
use App\Billing\Payment\ProviderSubscription;
use App\Billing\Repository\SubscriptionRepository;
use App\Workspace\Contract\Workspaces;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'billing.bus')]
#[WithMonologChannel('billing')]
final readonly class RefreshSubscriptionHandler
{
    public function __construct(
        private PaymentProvider $payments,
        private SubscriptionRepository $subscriptions,
        private Workspaces $workspaces,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshSubscription $message): void
    {
        // Not in the transaction: it would hold a connection for as long as Stripe takes to answer.
        $answer = $this->payments->fetchSubscription($message->stripeSubscriptionId);

        $this->em->wrapInTransaction(function () use ($answer): void {
            $subscription = $this->subscriptions->find($answer->id);

            if (null !== $subscription) {
                $subscription->refresh($answer->plan, $answer->status);

                return;
            }

            $this->saveNewOrLogRefusal($answer);
        });
    }

    private function saveNewOrLogRefusal(ProviderSubscription $answer): void
    {
        if (null === $answer->workspaceId || !$this->workspaces->exists($answer->workspaceId)) {
            $this->logger->error('Stripe subscription names no workspace of ours', [
                'subscription_id' => $answer->id,
                'workspace_id' => $answer->workspaceId?->toRfc4122(),
            ]);

            return;
        }

        $current = $this->subscriptions->findNotCanceledOf($answer->workspaceId);

        if (null !== $current) {
            $this->logger->error('Second Stripe subscription for a workspace not written', [
                'workspace_id' => $answer->workspaceId->toRfc4122(),
                'subscription_id' => $answer->id,
                'current_subscription_id' => $current->getStripeSubscriptionId(),
            ]);

            return;
        }

        $this->em->persist(new Subscription(
            stripeSubscriptionId: $answer->id,
            workspaceId: $answer->workspaceId,
            plan: $answer->plan,
            status: $answer->status,
        ));
    }
}
