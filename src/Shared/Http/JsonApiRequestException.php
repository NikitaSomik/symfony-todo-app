<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A request the API rejects before it reaches the controller, carrying the errors to report as they are.
 */
final class JsonApiRequestException extends \RuntimeException implements HttpExceptionInterface
{
    /**
     * @param non-empty-list<JsonApiError> $errors
     */
    private function __construct(
        private readonly int $statusCode,
        public readonly array $errors,
    ) {
        parent::__construct($errors[0]->detail);
    }

    public static function of(int $statusCode, JsonApiError $error, JsonApiError ...$errors): self
    {
        return new self($statusCode, [$error, ...array_values($errors)]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
