<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final class ApiRequestFormatListener
{
    /**
     * Without this the error renderer falls back to "html" for clients that send no usable
     * Accept header, and an API caller receives an HTML page instead of JSON.
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $request->setRequestFormat('json');
        }
    }
}
