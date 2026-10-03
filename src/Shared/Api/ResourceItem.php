<?php

declare(strict_types=1);

namespace App\Shared\Api;

final readonly class ResourceItem
{
    /**
     * @param array<string, mixed>                                                                     $attributes
     * @param array<string, array{data: array<string, string>|array<int, array<string, string>>|null}> $relationships
     * @param array<string, string>                                                                    $links
     */
    public function __construct(
        public string $type,
        public string|int $id,
        public array $attributes = [],
        public array $relationships = [],
        public array $links = [],
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

        if ([] !== $this->links) {
            $payload['links'] = $this->links;
        }

        return $payload;
    }
}
