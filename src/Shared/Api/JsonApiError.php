<?php

declare(strict_types=1);

namespace App\Shared\Api;

final readonly class JsonApiError
{
    public function __construct(
        public string $status,
        public string $message,
        public ?string $field = null,
    ) {
    }

    /**
     * @return array{status: string, message: string, field?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'message' => $this->message,
            'field' => $this->field,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
