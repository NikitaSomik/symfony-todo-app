<?php

declare(strict_types=1);

namespace App\Billing\Api\Documentation;

use App\Shared\Api\Documentation\JsonApiObjectSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'jsonapi', ref: new Model(type: JsonApiObjectSchema::class)),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'type', type: 'string', example: 'checkout_sessions'),
                new OA\Property(property: 'id', type: 'string', example: 'cs_test_a1b2c3'),
                new OA\Property(
                    property: 'attributes',
                    properties: [
                        new OA\Property(property: 'url', type: 'string', format: 'uri', example: 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3'),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    ]
)]
final class CheckoutSessionResponseSchema
{
}
