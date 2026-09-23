<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Api\Documentation;

use App\Shared\Api\Documentation\CollectionLinksSchema;
use App\Shared\Api\Documentation\JsonApiObjectSchema;
use App\Shared\AuditLog\Resource\AuditLogResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'jsonapi', ref: new Model(type: JsonApiObjectSchema::class)),
        new OA\Property(property: 'links', ref: new Model(type: CollectionLinksSchema::class)),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: AuditLogResource::class))),
    ]
)]
final class AuditLogCollectionResponseSchema
{
}
