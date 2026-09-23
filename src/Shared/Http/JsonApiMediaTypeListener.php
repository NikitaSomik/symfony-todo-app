<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonApiMediaTypeListener
{
    private const string API_PATH_PREFIX = '/api/v1/';

    /**
     * Every JSON body the API sends is a JSON:API document, error pages rendered by the framework included,
     * so the media type is set in one place instead of on each response.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), self::API_PATH_PREFIX)) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if (str_starts_with((string) $headers->get('Content-Type'), 'application/json')) {
            $headers->set('Content-Type', JsonApiResponse::MEDIA_TYPE);
        }
    }
}
