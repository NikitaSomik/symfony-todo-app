<?php

declare(strict_types=1);

namespace App\Fixtures\Billing;

use App\Billing\Entity\Payer;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Payer>
 */
final class PayerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Payer::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'workspaceId' => Uuid::v7(),
            'stripeCustomerId' => 'cus_'.Uuid::v7()->toBase58(),
        ];
    }
}
