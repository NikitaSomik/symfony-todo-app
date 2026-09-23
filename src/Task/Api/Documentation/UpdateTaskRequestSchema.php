<?php

declare(strict_types=1);

namespace App\Task\Api\Documentation;

use App\Task\DTO\UpdateTaskDTO;
use App\Task\Resource\TaskResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['type', 'id'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: [TaskResource::TYPE]),
                new OA\Property(property: 'id', description: 'Must match the id in the URL', type: 'string', format: 'uuid'),
                new OA\Property(property: 'attributes', ref: new Model(type: UpdateTaskDTO::class)),
            ],
            type: 'object',
        ),
    ]
)]
final class UpdateTaskRequestSchema
{
}
