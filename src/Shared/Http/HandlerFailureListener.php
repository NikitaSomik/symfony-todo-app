<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * A command bus wraps whatever its handler throws. Unwrapped, the exception is answered as it
 * is mapped in framework.exceptions, as if the controller had called the handler itself.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
final class HandlerFailureListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HandlerFailedException) {
            return;
        }

        // A command has one handler, so there is one exception to answer.
        $wrapped = $exception->getWrappedExceptions();
        $event->setThrowable(reset($wrapped) ?: $exception);
    }
}
