<?php

declare(strict_types=1);

namespace App\Task\Api\Documentation;

use App\Shared\Api\Documentation\JsonApiObjectSchema;
use App\Task\Resource\TaskResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'jsonapi', ref: new Model(type: JsonApiObjectSchema::class)),
        new OA\Property(property: 'data', ref: new Model(type: TaskResource::class)),
    ]
)]
final class TaskResponseSchema
{
}
