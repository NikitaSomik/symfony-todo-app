<?php

declare(strict_types=1);

namespace App\Fixtures\Billing;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Subscription>
 */
final class SubscriptionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Subscription::class;
    }

    protected function defaults(): array|callable
    {
        return ['workspaceId' => Uuid::v7(), 'plan' => Plan::TEAM];
    }
}
