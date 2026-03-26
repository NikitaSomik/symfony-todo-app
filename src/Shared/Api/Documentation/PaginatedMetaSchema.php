<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'page', ref: new Model(type: PageMetaSchema::class)),
    ]
)]
final class PaginatedMetaSchema
{
}
