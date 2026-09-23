<?php

declare(strict_types=1);

namespace App\Task\Api\Documentation;

use App\Task\DTO\CreateTaskDTO;
use App\Task\Resource\TaskResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['type', 'attributes'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: [TaskResource::TYPE]),
                new OA\Property(property: 'attributes', ref: new Model(type: CreateTaskDTO::class)),
            ],
            type: 'object',
        ),
    ]
)]
final class CreateTaskRequestSchema
{
}
