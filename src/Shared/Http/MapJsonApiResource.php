<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Reads the request body as a JSON:API document holding one resource object of the given type.
 *
 * On a DTO argument the resource's attributes are mapped and validated into it. On a ResourceDocument
 * argument the document is handed over as read, for a PATCH that must merge it with the current state.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapJsonApiResource extends ValueResolver
{
    /**
     * @param string      $type        the resource type the endpoint accepts, such as "tasks"
     * @param string|null $idFromRoute the route attribute the resource's "id" must match; null when creating,
     *                                 where a client-generated id is refused
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $idFromRoute = null,
    ) {
        parent::__construct(JsonApiResourceValueResolver::class);
    }
}
