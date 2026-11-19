<?php

declare(strict_types=1);

namespace App\Fixtures\Billing;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
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
        return [
            'stripeSubscriptionId' => 'sub_'.Uuid::v7()->toBase58(),
            'payer' => PayerFactory::new(),
            'plan' => Plan::TEAM,
            'status' => SubscriptionStatus::ACTIVE,
            'updatedAt' => new \DateTimeImmutable(),
        ];
    }

    /** A test names the workspace; the subscription belongs to that workspace's payer, made if it has none. */
    protected function initialize(): static
    {
        return $this->beforeInstantiate(static function (array $attributes): array {
            if (isset($attributes['workspaceId'])) {
                $attributes['payer'] = PayerFactory::findOrCreate(['workspaceId' => $attributes['workspaceId']]);
                unset($attributes['workspaceId']);
            }

            return $attributes;
        });
    }
}
