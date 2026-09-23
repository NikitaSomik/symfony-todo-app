<?php

declare(strict_types=1);

namespace App\Shared\Api\Documentation;

use App\Shared\Api\JsonApiResponse;
use OpenApi\Attributes as OA;

/**
 * A request or response body in the JSON:API media type; the counterpart of OA\JsonContent.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class JsonApiContent extends OA\MediaType
{
    public function __construct(object|string $ref)
    {
        parent::__construct(mediaType: JsonApiResponse::MEDIA_TYPE, schema: new OA\Schema(ref: $ref));
    }
}
