<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HTTP requires every 401 to carry a WWW-Authenticate challenge.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class UnauthorizedChallengeListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode() && !$response->headers->has('WWW-Authenticate')) {
            $response->headers->set('WWW-Authenticate', 'Bearer');
        }
    }
}
