<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Auth\Exception\EmailAlreadyTakenException;
use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof EmailAlreadyTakenException) {
            $event->setResponse(JsonApiResponse::error(
                [new JsonApiError((string) Response::HTTP_CONFLICT, $throwable->getMessage())],
                Response::HTTP_CONFLICT,
            ));

            return;
        }

        $validationException = $this->validationException($throwable);
        if (null !== $validationException) {
            $errors = [];

            foreach ($validationException->getViolations() as $violation) {
                $errors[] = new JsonApiError(
                    (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                    $violation->getMessage(),
                    '' !== $violation->getPropertyPath() ? $violation->getPropertyPath() : null,
                );
            }

            $event->setResponse(JsonApiResponse::error($errors, Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();

            $event->setResponse(JsonApiResponse::error(
                [new JsonApiError((string) $status, $this->messageForHttpException($throwable))],
                $status,
            ));

            return;
        }

        $event->setResponse(JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_INTERNAL_SERVER_ERROR, 'Server Error.')],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ));
    }

    private function validationException(Throwable $throwable): ?ValidationFailedException
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
            default => Response::$statusTexts[$exception->getStatusCode()] ?? 'Error.',
        };
    }
}
