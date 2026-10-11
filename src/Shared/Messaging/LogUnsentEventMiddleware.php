<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * An event leaves after its change has committed, so a broker that cannot take it must not fail
 * the request: the event is lost, and this record is how anyone learns of it.
 */
final readonly class LogUnsentEventMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (TransportException $exception) {
            $this->logger->error('An event could not be published after its change was committed.', [
                'event' => $envelope->getMessage()::class,
                'exception' => $exception,
            ]);

            return $envelope;
        }
    }
}
