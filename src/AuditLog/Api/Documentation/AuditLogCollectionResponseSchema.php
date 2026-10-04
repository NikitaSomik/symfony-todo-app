<?php

declare(strict_types=1);

namespace App\AuditLog\Api\Documentation;

use App\AuditLog\Resource\AuditLogResource;
use App\Shared\Api\Documentation\CollectionLinksSchema;
use App\Shared\Api\Documentation\JsonApiObjectSchema;
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
