<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Messaging;

use App\Shared\Messaging\LogUnsentEventMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class LogUnsentEventMiddlewareTest extends TestCase
{
    private function stackThatThrows(\Throwable $exception): StackInterface
    {
        return new StackMiddleware(new class($exception) implements MiddlewareInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw $this->exception;
            }
        });
    }

    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, context: array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'context' => $context];
            }
        };
    }

    #[Test]
    public function eventThatCannotReachItsTransportShouldBeLoggedInsteadOfFailingTheRequest(): void
    {
        $logger = $this->logger();
        $envelope = new Envelope(new \stdClass());

        $result = (new LogUnsentEventMiddleware($logger))->handle($envelope, $this->stackThatThrows(new TransportException('The broker is down.')));

        self::assertSame($envelope, $result);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(\stdClass::class, $logger->records[0]['context']['event']);
    }

    #[Test]
    public function failureOfAListenerShouldNotBeHidden(): void
    {
        $this->expectException(\LogicException::class);

        (new LogUnsentEventMiddleware($this->logger()))->handle(new Envelope(new \stdClass()), $this->stackThatThrows(new \LogicException('A listener failed.')));
    }
}
