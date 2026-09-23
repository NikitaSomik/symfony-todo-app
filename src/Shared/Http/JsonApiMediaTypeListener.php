<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonApiMediaTypeListener
{
    private const string API_PATH_PREFIX = '/api/v1/';

    /**
     * A client that lists the JSON:API media type in Accept, but only with parameters this server cannot
     * honour, is refused with a 406. The only parameter accepted is "profile", which a server may ignore;
     * "ext" names extensions, and none is supported. A client that does not ask for the JSON:API media
     * type at all ("*\/*", "application/json" or no Accept) is answered as usual.
     *
     * Runs right after ApiRequestFormatListener, so the error is rendered as JSON.
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // The error page for this very refusal is rendered in a sub-request carrying the same headers.
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) {
            return;
        }

        $instances = array_filter(
            AcceptHeader::fromString($request->headers->get('Accept'))->all(),
            static fn (AcceptHeaderItem $item): bool => JsonApiResponse::MEDIA_TYPE === strtolower($item->getValue()),
        );

        if ([] === $instances || array_any($instances, self::isAcceptable(...))) {
            return;
        }

        throw JsonApiRequestException::of(Response::HTTP_NOT_ACCEPTABLE, JsonApiError::forHeader((string) Response::HTTP_NOT_ACCEPTABLE, sprintf('%s is only served without media type parameters other than "profile".', JsonApiResponse::MEDIA_TYPE), 'Accept'));
    }

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

    private static function isAcceptable(AcceptHeaderItem $item): bool
    {
        return [] === array_diff(array_keys($item->getAttributes()), ['profile']);
    }
}
