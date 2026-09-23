<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * The resource object of a JSON:API request document, after its structure has been checked.
 */
final readonly class ResourceDocument
{
    /**
     * @param array<string, mixed> $attributes only the attributes the client sent
     */
    public function __construct(
        public string $type,
        public ?string $id,
        public array $attributes,
    ) {
    }
}
