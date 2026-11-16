<?php

declare(strict_types=1);

namespace App\Billing\Handler;

use App\Billing\Entity\Subscription;
use App\Billing\Message\RefreshSubscription;
use App\Billing\Payment\PaymentProvider;
use App\Billing\Payment\ProviderSubscription;
use App\Billing\Repository\PayerRepository;
use App\Billing\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'billing.bus')]
#[WithMonologChannel('billing')]
final readonly class RefreshSubscriptionHandler
{
    public function __construct(
        private PaymentProvider $payments,
        private SubscriptionRepository $subscriptions,
        private PayerRepository $payers,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
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
                $subscription->refresh($answer->plan, $answer->status, $this->clock->now());

                return;
            }

            $this->saveNewOrLogRefusal($answer);
        });
    }

    private function saveNewOrLogRefusal(ProviderSubscription $answer): void
    {
        $workspaceId = $this->payers->findWorkspaceIdOfCustomer($answer->customerId);

        if (null === $workspaceId) {
            $this->logger->error('Stripe subscription of an unknown customer not written', [
                'subscription_id' => $answer->id,
                'customer_id' => $answer->customerId,
            ]);

            return;
        }

        $current = $this->subscriptions->findNotCanceledOf($workspaceId);

        if (null !== $current) {
            $this->logger->error('Second Stripe subscription for a workspace not written', [
                'workspace_id' => $workspaceId->toRfc4122(),
                'subscription_id' => $answer->id,
                'current_subscription_id' => $current->getStripeSubscriptionId(),
            ]);

            return;
        }

        $this->em->persist(new Subscription(
            stripeSubscriptionId: $answer->id,
            workspaceId: $workspaceId,
            plan: $answer->plan,
            status: $answer->status,
            updatedAt: $this->clock->now(),
        ));
    }
}
