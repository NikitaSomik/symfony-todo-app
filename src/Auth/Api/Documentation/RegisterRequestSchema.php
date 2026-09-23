<?php

declare(strict_types=1);

namespace App\Auth\Api\Documentation;

use App\Auth\DTO\RegisterDTO;
use App\Auth\Resource\UserResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['type', 'attributes'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: [UserResource::TYPE]),
                new OA\Property(property: 'attributes', ref: new Model(type: RegisterDTO::class)),
            ],
            type: 'object',
        ),
    ]
)]
final class RegisterRequestSchema
{
}
