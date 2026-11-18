<?php

declare(strict_types=1);

namespace App\Billing\Handler;

use App\Billing\Exception\SecondSubscriptionException;
use App\Billing\Message\SyncSubscription;
use App\Billing\Payment\PaymentProvider;
use App\Billing\Repository\PayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'billing.bus')]
#[WithMonologChannel('billing')]
final readonly class SyncSubscriptionHandler
{
    public function __construct(
        private PaymentProvider $payments,
        private PayerRepository $payers,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncSubscription $message): void
    {
        // Not in the transaction: it would hold a connection for as long as Stripe takes to answer.
        $answer = $this->payments->fetchSubscription($message->stripeSubscriptionId);

        $payer = $this->payers->findOfCustomer($answer->customerId);
        if (null === $payer) {
            $this->logger->error('Stripe subscription of an unknown customer not written', [
                'subscription_id' => $answer->id,
                'customer_id' => $answer->customerId,
            ]);

            return;
        }

        $this->em->wrapInTransaction(function () use ($payer, $answer): void {
            try {
                $payer->syncSubscription($answer->id, $answer->plan, $answer->status, $this->clock->now());
            } catch (SecondSubscriptionException $refusal) {
                $this->logger->error('Second Stripe subscription for a workspace not written', [
                    'workspace_id' => $refusal->workspaceId->toRfc4122(),
                    'subscription_id' => $refusal->subscriptionId,
                    'not_canceled_subscription_id' => $refusal->notCanceledSubscriptionId,
                ]);
            }
        });
    }
}
