<?php

declare(strict_types=1);

namespace App\Shared\Activity\Api\Documentation;

use App\Shared\Activity\Resource\ActivityLogResource;
use App\Shared\Api\Documentation\CollectionLinksSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'links', ref: new Model(type: CollectionLinksSchema::class)),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: ActivityLogResource::class))),
    ]
)]
final class ActivityLogCollectionResponseSchema
{
}
