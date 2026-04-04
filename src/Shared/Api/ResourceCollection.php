<?php

declare(strict_types=1);

namespace App\Shared\Api;

readonly class ResourceCollection
{
    /**
     * @param ResourceItem[]             $items
     * @param array<string, string|null> $links
     */
    public function __construct(
        public array $items,
        public array $links = [],
    ) {
    }
}
