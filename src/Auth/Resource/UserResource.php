<?php

declare(strict_types=1);

namespace App\Auth\Resource;

use App\Auth\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
final class UserResource
{
    /** @return array{id: int|null, email: string, created_at: string} */
    public static function fromEntity(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
