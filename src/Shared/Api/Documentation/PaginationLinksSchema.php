<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'first', type: 'string', example: '/api/v1/tasks?page[number]=1&page[size]=20'),
        new OA\Property(property: 'last', type: 'string', example: '/api/v1/tasks?page[number]=8&page[size]=20'),
        new OA\Property(property: 'prev', type: 'string', example: '/api/v1/tasks?page[number]=1&page[size]=20', nullable: true),
        new OA\Property(property: 'next', type: 'string', example: '/api/v1/tasks?page[number]=2&page[size]=20', nullable: true),
    ]
)]
final class PaginationLinksSchema
{
}
