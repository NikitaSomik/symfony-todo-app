<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Below 0 on purpose: setting a response stops propagation, so a higher priority
            // would skip Symfony's ErrorListener::logKernelException() and 500s would never be logged.
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $throwable = $event->getThrowable();

        $validationException = $this->validationException($throwable);
        if (null !== $validationException) {
            $errors = [];

            foreach ($validationException->getViolations() as $violation) {
                $errors[] = new JsonApiError(
                    (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                    (string) $violation->getMessage(),
                    '' !== $violation->getPropertyPath() ? $violation->getPropertyPath() : null,
                );
            }

            $event->setResponse(JsonApiResponse::error($errors, Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $mapped = $throwable->getPrevious();

            $event->setResponse(JsonApiResponse::error(
                [new JsonApiError(
                    (string) $status,
                    $mapped instanceof ClientFacingException ? $mapped->getMessage() : $this->messageForHttpException($throwable),
                )],
                $status,
            ));

            return;
        }

        $event->setResponse(JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_INTERNAL_SERVER_ERROR, 'Server Error.')],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ));
    }

    private function validationException(\Throwable $throwable): ?ValidationFailedException
    {
        if ($throwable instanceof ValidationFailedException) {
            return $throwable;
        }

        $previous = $throwable->getPrevious();

        return $previous instanceof ValidationFailedException ? $previous : null;
    }

    private function messageForHttpException(HttpExceptionInterface $exception): string
    {
        return match ($exception->getStatusCode()) {
            Response::HTTP_UNAUTHORIZED => 'Unauthorized.',
            Response::HTTP_FORBIDDEN => 'Forbidden.',
            Response::HTTP_NOT_FOUND => 'Not Found.',
            Response::HTTP_BAD_REQUEST => 'Bad Request.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            default => Response::$statusTexts[$exception->getStatusCode()] ?? 'Error.',
        };
    }
}
