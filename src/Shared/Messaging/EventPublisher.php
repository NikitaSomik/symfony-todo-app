<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Announces a fact to other modules. Call it after the transaction that made the fact true has
 * committed: inside it, a listener could act on a change that is then rolled back.
 */
final readonly class EventPublisher
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(object $event): void
    {
        try {
            $this->eventBus->dispatch($event);
        } catch (TransportException $exception) {
            // The change is committed; failing the request now would tell the client it was not.
            // The announcement is lost instead, and this record is how anyone learns of it.
            $this->logger->error('An event could not be published after its change was committed.', [
                'event' => $event::class,
                'exception' => $exception,
            ]);
        }
    }
}
