<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'self', type: 'string', example: '/api/v1/tasks/0195a6b4-6f15-7d4b-b2c1-05b2a3d6e7f8/audit-logs'),
    ]
)]
final class CollectionLinksSchema
{
}
