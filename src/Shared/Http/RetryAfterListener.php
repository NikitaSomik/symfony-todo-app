<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs after the exception map has wrapped a domain exception in an HttpException (priority 0)
 * and before the error page is rendered from it (-128), so the header goes out with the status.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class RetryAfterListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof HttpException) {
            return;
        }

        $retryable = $throwable instanceof RetryableException ? $throwable : $throwable->getPrevious();

        if (!$retryable instanceof RetryableException) {
            return;
        }

        $throwable->setHeaders($throwable->getHeaders() + ['Retry-After' => (string) $retryable->retryAfterSeconds()]);
    }
}
