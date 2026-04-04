<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'self', type: 'string', example: '/api/v1/tasks/1/activity'),
    ]
)]
final class CollectionLinksSchema
{
}
