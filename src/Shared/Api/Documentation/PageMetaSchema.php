<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'current', type: 'integer', example: 1),
        new OA\Property(property: 'size', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 156),
        new OA\Property(property: 'last', type: 'integer', example: 8),
    ]
)]
final class PageMetaSchema
{
}
