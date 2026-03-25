<?php

declare(strict_types=1);

namespace App\Shared\Api;

final readonly class ResourceItem
{
    /**
     * @param array<string, mixed>                                                                     $attributes
     * @param array<string, array{data: array<string, string>|array<int, array<string, string>>|null}> $relationships
     */
    public function __construct(
        public string $type,
        public string|int $id,
        public array $attributes = [],
        public array $relationships = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->type,
            'id' => (string) $this->id,
            'attributes' => $this->attributes,
        ];

        if ([] !== $this->relationships) {
            $payload['relationships'] = $this->relationships;
        }

        return $payload;
    }
}
