<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Messaging;

use App\Shared\Messaging\EventPublisher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventPublisherTest extends TestCase
{
    private function busThatThrows(\Throwable $exception): MessageBusInterface
    {
        return new class($exception) implements MessageBusInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw $this->exception;
            }
        };
    }

    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    #[Test]
    public function eventThatCannotReachItsTransportShouldBeLoggedInsteadOfFailingTheRequest(): void
    {
        $logger = $this->logger();
        $publisher = new EventPublisher($this->busThatThrows(new TransportException('The broker is down.')), $logger);

        $publisher->publish(new \stdClass());

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(\stdClass::class, $logger->records[0]['context']['event']);
    }

    #[Test]
    public function failureOfAListenerShouldNotBeHidden(): void
    {
        $publisher = new EventPublisher($this->busThatThrows(new \LogicException('A listener failed.')), $this->logger());

        $this->expectException(\LogicException::class);

        $publisher->publish(new \stdClass());
    }
}
