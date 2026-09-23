<?php

declare(strict_types=1);

namespace App\Task\Api\Documentation;

use App\Shared\Api\Documentation\JsonApiObjectSchema;
use App\Shared\Api\Documentation\PaginatedMetaSchema;
use App\Shared\Api\Documentation\PaginationLinksSchema;
use App\Task\Resource\TaskResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'jsonapi', ref: new Model(type: JsonApiObjectSchema::class)),
        new OA\Property(property: 'links', ref: new Model(type: PaginationLinksSchema::class)),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: TaskResource::class))),
        new OA\Property(property: 'meta', ref: new Model(type: PaginatedMetaSchema::class)),
    ]
)]
final class TaskCollectionResponseSchema
{
}
