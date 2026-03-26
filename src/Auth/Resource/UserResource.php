<?php

declare(strict_types=1);

namespace App\Auth\Resource;

use App\Auth\Entity\User;
use App\Shared\Api\ResourceItem;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'users'),
        new OA\Property(property: 'id', type: 'string', example: '1'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
    ]
)]
final class UserResource
{
    public static function toItem(User $user): ResourceItem
    {
        $id = $user->getId();

        if (null === $id) {
            throw new \LogicException('User resource cannot be created for an entity without id.');
        }

        return new ResourceItem(
            type: 'users',
            id: $id,
            attributes: [
                'email' => $user->getEmail(),
                'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        );
    }
}
