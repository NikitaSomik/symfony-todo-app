<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Message\RefreshSubscription;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RefreshFailureListenerTest extends KernelTestCase
{
    /** As the worker does: Messenger's own listener decides from the retries already made whether the message is retried. */
    private function failed(object $message, int $retriesMade): void
    {
        $envelope = new Envelope($message, [new RedeliveryStamp($retriesMade)]);
        $event = new WorkerMessageFailedEvent($envelope, 'stripe_events', PaymentProviderUnavailableException::because(new \RuntimeException('Stripe is down.')));

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->dispatch($event);
    }

    /** @return list<LogRecord> */
    private function billingErrors(): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return array_values(array_filter($logs->getRecords(), static fn (LogRecord $record): bool => 'billing' === $record->channel && Level::Error === $record->level));
    }

    #[Test]
    public function aRefreshThatFailedItsThirdRetryShouldBeLoggedAsAnErrorWithTheSubscriptionsId(): void
    {
        $this->failed(new RefreshSubscription('sub_paid'), retriesMade: 3);

        $errors = $this->billingErrors();
        self::assertCount(1, $errors);
        self::assertSame('Stripe subscription not refreshed after its retries', $errors[0]->message);
        self::assertSame('sub_paid', $errors[0]->context['subscription_id']);
        self::assertInstanceOf(PaymentProviderUnavailableException::class, $errors[0]->context['exception']);
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([2])]
    public function aRefreshThatWillStillBeRetriedShouldNotBeLoggedAsAnError(int $retriesMade): void
    {
        $this->failed(new RefreshSubscription('sub_paid'), $retriesMade);

        self::assertSame([], $this->billingErrors());
    }

    #[Test]
    public function anotherMessageThatFailedShouldNotBeLoggedAsARefresh(): void
    {
        $this->failed(new \stdClass(), retriesMade: 3);

        self::assertSame([], $this->billingErrors());
    }
}
