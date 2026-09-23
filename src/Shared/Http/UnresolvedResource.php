<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Stands in for a #[MapJsonApiResource] argument until the request body is read, once authorization has run.
 *
 * @internal
 */
final readonly class UnresolvedResource
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public MapJsonApiResource $attribute,
        public string $class,
    ) {
    }
}
