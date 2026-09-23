<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns the request id to the client, so a reported problem can be found in the logs.
 *
 * Every response gets it as a header. An error document also quotes it in "meta", where people
 * reporting a problem actually look. Doing it here, once, covers errors from any source:
 * the exception normalizer and the security handlers alike.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class RequestIdListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $id = RequestId::of($event->getRequest());
        $response = $event->getResponse();

        $response->headers->set(RequestId::HEADER, $id);

        if ($response->getStatusCode() >= Response::HTTP_BAD_REQUEST) {
            $this->quoteInErrorDocument($response, $id);
        }
    }

    private function quoteInErrorDocument(Response $response, string $id): void
    {
        if (!str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return;
        }

        $document = json_decode((string) $response->getContent(), true);

        if (!\is_array($document) || !isset($document['errors'])) {
            return;
        }

        // Rebuilt rather than appended, to keep the order of the members: jsonapi, meta, errors.
        $errors = $document['errors'];
        unset($document['errors']);
        $document['meta'] = ['request_id' => $id] + (\is_array($document['meta'] ?? null) ? $document['meta'] : []);
        $document['errors'] = $errors;

        $response->setContent(json_encode($document, JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_THROW_ON_ERROR));
    }
}
