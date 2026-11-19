<?php

declare(strict_types=1);

namespace App\Billing\Api\Documentation;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Shared\Api\Documentation\JsonApiObjectSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'jsonapi', ref: new Model(type: JsonApiObjectSchema::class)),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'type', type: 'string', example: 'billing'),
                new OA\Property(property: 'id', description: 'The workspace\'s id', type: 'string', format: 'uuid', example: '01933b8e-6a4f-7c2d-9e1f-3a5b7c9d1e2f'),
                new OA\Property(
                    property: 'attributes',
                    properties: [
                        new OA\Property(property: 'plan', description: 'The plan the workspace has now: a paid plan only while its subscription is `active` or in `grace`', type: 'string', enum: [Plan::FREE, Plan::TEAM, Plan::BUSINESS], example: 'team'),
                        new OA\Property(property: 'status', description: 'The status of the workspace\'s subscription, `canceled` when it has none left but had one; `null` when it never had one', type: 'string', enum: [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE, SubscriptionStatus::GRACE, SubscriptionStatus::SUSPENDED, SubscriptionStatus::CANCELED], example: 'active', nullable: true),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    ]
)]
final class BillingResponseSchema
{
}
