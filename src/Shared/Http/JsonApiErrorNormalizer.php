<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class JsonApiErrorNormalizer implements NormalizerInterface
{
    /**
     * @return array{errors: list<array{status: string, message: string, field?: string}>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        \assert($data instanceof FlattenException);

        $throwable = $context['exception'] ?? null;

        $validationException = $this->validationException($throwable);
        if (null !== $validationException) {
            $errors = [];

            foreach ($validationException->getViolations() as $violation) {
                $errors[] = new JsonApiError(
                    (string) $data->getStatusCode(),
                    (string) $violation->getMessage(),
                    '' !== $violation->getPropertyPath() ? $violation->getPropertyPath() : null,
                );
            }

            return $this->toPayload($errors);
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $mapped = $throwable->getPrevious();

            return $this->toPayload([new JsonApiError(
                (string) $status,
                $mapped instanceof ClientFacingException ? $mapped->getMessage() : $this->messageForStatus($status),
            )]);
        }

        return $this->toPayload([new JsonApiError((string) Response::HTTP_INTERNAL_SERVER_ERROR, 'Server Error.')]);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [FlattenException::class => true];
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
     * @param JsonApiError[] $errors
     *
     * @return array{errors: list<array{status: string, message: string, field?: string}>}
     */
    private function toPayload(array $errors): array
    {
        return ['errors' => array_values(array_map(static fn (JsonApiError $error): array => $error->toArray(), $errors))];
    }

    private function messageForStatus(int $status): string
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
