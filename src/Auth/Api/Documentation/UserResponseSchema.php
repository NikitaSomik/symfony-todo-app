<?php

declare(strict_types=1);

namespace App\Auth\Api\Documentation;

use App\Auth\Resource\UserResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'data', ref: new Model(type: UserResource::class)),
    ]
)]
final class UserResponseSchema
{
}
