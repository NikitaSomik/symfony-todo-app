<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Command\SyncSubscription;
use App\Billing\Exception\PaymentProviderUnavailableException;
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

/** A sync that fails is left to Messenger (0038): these pin that its log and its retries are what we rely on. */
final class SyncFailureTest extends KernelTestCase
{
    /** As the worker does: Messenger's own listener decides from the retries already made whether the message is retried. */
    private function failed(int $retriesMade): void
    {
        $envelope = new Envelope(new SyncSubscription('sub_paid'), [new RedeliveryStamp($retriesMade)]);
        $event = new WorkerMessageFailedEvent($envelope, 'stripe_events', PaymentProviderUnavailableException::because(new \RuntimeException('Stripe is down.')));

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->dispatch($event);
    }

    /** @return list<LogRecord> */
    private function records(string $channel, Level $level): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return array_values(array_filter($logs->getRecords(), static fn (LogRecord $record): bool => $channel === $record->channel && $level === $record->level));
    }

    #[Test]
    public function aSyncThatFailedItsThirdRetryShouldBeLoggedByMessengerAsCritical(): void
    {
        $this->failed(retriesMade: 3);

        $critical = $this->records('messenger', Level::Critical);
        self::assertCount(1, $critical);
        self::assertSame(SyncSubscription::class, $critical[0]->context['class']);
        self::assertInstanceOf(PaymentProviderUnavailableException::class, $critical[0]->context['exception']);
        self::assertSame([], $this->records('billing', Level::Error));
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([2])]
    public function aSyncThatWillStillBeRetriedShouldOnlyBeAWarning(int $retriesMade): void
    {
        $this->failed($retriesMade);

        self::assertCount(1, $this->records('messenger', Level::Warning));
        self::assertSame([], $this->records('messenger', Level::Critical));
    }
}
