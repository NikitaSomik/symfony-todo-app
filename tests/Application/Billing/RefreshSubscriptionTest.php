<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Entity\Payer;
use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Message\RefreshSubscription;
use App\Billing\Payment\ProviderSubscription;
use App\Billing\Repository\SubscriptionRepository;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\PaymentProviderStub;
use App\Workspace\Entity\Workspace;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class RefreshSubscriptionTest extends ApiTestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = WorkspaceFactory::createOne();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist(new Payer(workspaceId: $this->workspace->getId(), stripeCustomerId: 'cus_workspace'));
        $em->flush();
    }

    private function stripe(): PaymentProviderStub
    {
        return static::getContainer()->get(PaymentProviderStub::class);
    }

    private function subscriptions(): SubscriptionRepository
    {
        return static::getContainer()->get(SubscriptionRepository::class);
    }

    private function stripeAnswers(string $subscriptionId, SubscriptionStatus $status, Plan $plan = Plan::TEAM, string $customerId = 'cus_workspace'): void
    {
        $this->stripe()->subscriptions[$subscriptionId] = new ProviderSubscription(
            id: $subscriptionId,
            customerId: $customerId,
            plan: $plan,
            status: $status,
        );
    }

    /** As the worker does: the received stamp makes the bus handle the message instead of queueing it again. */
    private function deliver(string $subscriptionId): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('billing.bus');
        $bus->dispatch(new Envelope(new RefreshSubscription($subscriptionId), [new ReceivedStamp('stripe_events')]));
    }

    /** @return list<Subscription> */
    private function rowsOf(Workspace $workspace): array
    {
        return array_values($this->subscriptions()->findBy(['workspaceId' => $workspace->getId()], ['stripeSubscriptionId' => 'ASC']));
    }

    private function onlyRow(): Subscription
    {
        $rows = $this->rowsOf($this->workspace);
        self::assertCount(1, $rows);

        return $rows[0];
    }

    private function planOfTheWorkspace(): Plan
    {
        return $this->subscriptions()->planOf($this->workspace->getId());
    }

    /** @return list<LogRecord> */
    private function billingErrors(): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return array_values(array_filter($logs->getRecords(), static fn (LogRecord $record): bool => 'billing' === $record->channel && Level::Error === $record->level));
    }

    #[Test]
    public function anActiveSubscriptionOfAWorkspaceOnFreeShouldGiveItThePlan(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE, Plan::BUSINESS);

        $this->deliver('sub_paid');

        $row = $this->onlyRow();
        self::assertSame('sub_paid', $row->getStripeSubscriptionId());
        self::assertSame(Plan::BUSINESS, $row->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $row->getStatus());
        self::assertSame(Plan::BUSINESS, $this->planOfTheWorkspace());
        self::assertSame(['sub_paid'], $this->stripe()->fetchedSubscriptions);
    }

    #[Test]
    public function theSameMessageTwiceShouldLeaveOneRowWithTheSameValues(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE);

        $this->deliver('sub_paid');
        $this->deliver('sub_paid');

        $row = $this->onlyRow();
        self::assertSame(Plan::TEAM, $row->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $row->getStatus());
    }

    #[Test]
    public function aLateEventShouldFetchAgainAndKeepWhatStripeAnswersNow(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE);
        $this->deliver('sub_paid');

        $this->deliver('sub_paid');

        self::assertSame(['sub_paid', 'sub_paid'], $this->stripe()->fetchedSubscriptions);
        self::assertSame(SubscriptionStatus::ACTIVE, $this->onlyRow()->getStatus());
        self::assertSame(Plan::TEAM, $this->planOfTheWorkspace());
    }

    #[Test]
    public function aPaymentStillAwaitedShouldKeepFreeUntilStripeReportsItPaid(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::PENDING);
        $this->deliver('sub_paid');

        self::assertSame(SubscriptionStatus::PENDING, $this->onlyRow()->getStatus());
        self::assertSame(Plan::FREE, $this->planOfTheWorkspace());

        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE);
        $this->deliver('sub_paid');

        self::assertSame(SubscriptionStatus::ACTIVE, $this->onlyRow()->getStatus());
        self::assertSame(Plan::TEAM, $this->planOfTheWorkspace());
    }

    #[Test]
    public function aFailedChargeStripeRetriesShouldKeepThePlan(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE);
        $this->deliver('sub_paid');

        $this->stripeAnswers('sub_paid', SubscriptionStatus::GRACE);
        $this->deliver('sub_paid');

        self::assertSame(SubscriptionStatus::GRACE, $this->onlyRow()->getStatus());
        self::assertSame(Plan::TEAM, $this->planOfTheWorkspace());
    }

    #[Test]
    public function aSuspendedSubscriptionShouldGiveFreeForNow(): void
    {
        $this->stripeAnswers('sub_paid', SubscriptionStatus::ACTIVE);
        $this->deliver('sub_paid');

        $this->stripeAnswers('sub_paid', SubscriptionStatus::SUSPENDED);
        $this->deliver('sub_paid');

        self::assertSame(SubscriptionStatus::SUSPENDED, $this->onlyRow()->getStatus());
        self::assertSame(Plan::FREE, $this->planOfTheWorkspace());
    }

    #[Test]
    public function aCancelledSubscriptionShouldKeepItsRowAndTheWorkspaceFallBackToFree(): void
    {
        $this->stripeAnswers('sub_first', SubscriptionStatus::ACTIVE);
        $this->deliver('sub_first');

        $this->stripeAnswers('sub_first', SubscriptionStatus::CANCELED);
        $this->deliver('sub_first');

        self::assertSame(SubscriptionStatus::CANCELED, $this->onlyRow()->getStatus());
        self::assertSame(Plan::FREE, $this->planOfTheWorkspace());
    }

    #[Test]
    public function aNewSubscriptionAfterACancelledOneShouldGetARowOfItsOwnAndThePlan(): void
    {
        $this->stripeAnswers('sub_first', SubscriptionStatus::CANCELED);
        $this->deliver('sub_first');

        $this->stripeAnswers('sub_second', SubscriptionStatus::ACTIVE, Plan::BUSINESS);
        $this->deliver('sub_second');

        $rows = $this->rowsOf($this->workspace);
        self::assertCount(2, $rows);
        self::assertSame(['sub_first', SubscriptionStatus::CANCELED], [$rows[0]->getStripeSubscriptionId(), $rows[0]->getStatus()]);
        self::assertSame(['sub_second', SubscriptionStatus::ACTIVE], [$rows[1]->getStripeSubscriptionId(), $rows[1]->getStatus()]);
        self::assertSame(Plan::BUSINESS, $this->planOfTheWorkspace());
    }

    #[Test]
    #[TestWith([SubscriptionStatus::ACTIVE])]
    #[TestWith([SubscriptionStatus::PENDING])]
    public function aSecondSubscriptionBesideOneNotCancelledShouldNotBeWrittenAndBeLoggedWithBothIds(SubscriptionStatus $first): void
    {
        $this->stripeAnswers('sub_first', $first);
        $this->deliver('sub_first');

        $this->stripeAnswers('sub_second', SubscriptionStatus::ACTIVE, Plan::BUSINESS);
        $this->deliver('sub_second');

        self::assertSame('sub_first', $this->onlyRow()->getStripeSubscriptionId());
        $errors = $this->billingErrors();
        self::assertCount(1, $errors);
        self::assertSame('Second Stripe subscription for a workspace not written', $errors[0]->message);
        self::assertSame(
            ['workspace_id' => $this->workspace->getId()->toRfc4122(), 'subscription_id' => 'sub_second', 'current_subscription_id' => 'sub_first'],
            $errors[0]->context,
        );
    }

    #[Test]
    public function aSubscriptionOfACustomerWeDoNotKnowShouldBeLoggedAndNotWritten(): void
    {
        $this->stripeAnswers('sub_elsewhere', SubscriptionStatus::ACTIVE, customerId: 'cus_elsewhere');

        $this->deliver('sub_elsewhere');

        self::assertSame(0, $this->subscriptions()->count([]));
        $errors = $this->billingErrors();
        self::assertCount(1, $errors);
        self::assertSame('Stripe subscription of an unknown customer not written', $errors[0]->message);
        self::assertSame(['subscription_id' => 'sub_elsewhere', 'customer_id' => 'cus_elsewhere'], $errors[0]->context);
    }

    #[Test]
    public function aStripeThatIsUnavailableShouldFailTheMessageSoThatItIsRetried(): void
    {
        $this->stripe()->failure = PaymentProviderUnavailableException::because(new \RuntimeException('Stripe is down.'));

        try {
            $this->deliver('sub_paid');
            self::fail('A refresh Stripe did not answer was handled as if it had.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(PaymentProviderUnavailableException::class, $exception->getPrevious());
        }

        self::assertSame(0, $this->subscriptions()->count([]));
        self::assertSame([], $this->billingErrors());
    }

    #[Test]
    public function aPriceThatIsNoPlanShouldFailTheMessageSoThatItIsRetried(): void
    {
        $this->stripe()->failure = new \UnexpectedValueException('The price price_paid of the Stripe subscription sub_paid has no lookup key of a paid plan.');

        $this->expectException(HandlerFailedException::class);

        $this->deliver('sub_paid');
    }
}
