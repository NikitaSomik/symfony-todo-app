<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class JsonApiErrorNormalizer implements NormalizerInterface
{
    /**
     * @return array{jsonapi: array{version: string}, errors: list<array{status: string, detail: string, source?: array{pointer: string}|array{parameter: string}|array{header: string}}>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        \assert($data instanceof FlattenException);

        $throwable = $context['exception'] ?? null;
        $validationException = $this->validationException($throwable);

        if (null !== $validationException) {
            return $this->toPayload($this->violationErrors($validationException, $data->getStatusCode()));
        }

        if ($throwable instanceof JsonApiRequestException) {
            return $this->toPayload($throwable->errors);
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $mapped = $throwable->getPrevious();

            return $this->toPayload([JsonApiError::of(
                (string) $status,
                $mapped instanceof ClientFacingException ? $mapped->getMessage() : $this->detailForStatus($status),
            )]);
        }

        return $this->toPayload([JsonApiError::of((string) Response::HTTP_INTERNAL_SERVER_ERROR, 'Server Error.')]);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [FlattenException::class => true];
    }

    /**
     * @return list<JsonApiError>
     */
    private function violationErrors(ValidationFailedException $exception, int $status): array
    {
        $fromQuery = $exception->getValue() instanceof QueryPayload;
        $errors = [];

        foreach ($exception->getViolations() as $violation) {
            $path = $violation->getPropertyPath();
            $detail = (string) $violation->getMessage();

            $errors[] = match (true) {
                '' === $path => JsonApiError::of((string) $status, $detail),
                $fromQuery => JsonApiError::forParameter((string) $status, $detail, PropertyPath::toParameterName($path)),
                default => JsonApiError::forPointer((string) $status, $detail, PropertyPath::toJsonPointer($path)),
            };
        }

        return $errors;
    }

    private function validationException(mixed $throwable): ?ValidationFailedException
    {
        if ($throwable instanceof ValidationFailedException) {
            return $throwable;
        }

        $previous = $throwable instanceof \Throwable ? $throwable->getPrevious() : null;

        return $previous instanceof ValidationFailedException ? $previous : null;
    }

    /**
     * @param list<JsonApiError> $errors
     *
     * @return array{jsonapi: array{version: string}, errors: list<array{status: string, detail: string, source?: array{pointer: string}|array{parameter: string}|array{header: string}}>}
     */
    private function toPayload(array $errors): array
    {
        return ['jsonapi' => JsonApiResponse::JSONAPI, 'errors' => array_map(static fn (JsonApiError $error): array => $error->toArray(), $errors)];
    }

    private function detailForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'Unauthorized.',
            Response::HTTP_FORBIDDEN => 'Forbidden.',
            Response::HTTP_NOT_FOUND => 'Not Found.',
            Response::HTTP_BAD_REQUEST => 'Bad Request.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            default => Response::$statusTexts[$status] ?? 'Error.',
        };
    }
}
