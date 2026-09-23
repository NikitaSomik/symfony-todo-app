<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'The top-level "jsonapi" member every document carries.',
    properties: [
        new OA\Property(property: 'version', type: 'string', example: '1.1'),
    ]
)]
final class JsonApiObjectSchema
{
}
