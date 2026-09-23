<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns the request id to the client, so a reported problem can be found in the logs.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class RequestIdListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set(RequestId::HEADER, RequestId::of($event->getRequest()));
    }
}
