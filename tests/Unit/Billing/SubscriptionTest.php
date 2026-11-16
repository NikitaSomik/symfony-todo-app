<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SubscriptionTest extends TestCase
{
    private function subscription(Plan $plan, SubscriptionStatus $status): Subscription
    {
        return new Subscription(stripeSubscriptionId: 'sub_test', workspaceId: Uuid::v7(), plan: $plan, status: $status);
    }

    #[Test]
    #[TestWith([SubscriptionStatus::ACTIVE])]
    #[TestWith([SubscriptionStatus::GRACE])]
    public function aPaidOrRetriedSubscriptionShouldGrantItsPlan(SubscriptionStatus $status): void
    {
        self::assertSame(Plan::BUSINESS, $this->subscription(Plan::BUSINESS, $status)->grantedPlan());
    }

    #[Test]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    #[TestWith([SubscriptionStatus::CANCELED])]
    public function anUnpaidOrFinishedSubscriptionShouldGrantFree(SubscriptionStatus $status): void
    {
        self::assertSame(Plan::FREE, $this->subscription(Plan::BUSINESS, $status)->grantedPlan());
    }

    #[Test]
    public function aRefreshShouldTakeThePlanAndTheStatusStripeAnswered(): void
    {
        $subscription = $this->subscription(Plan::TEAM, SubscriptionStatus::PENDING);

        $subscription->refresh(Plan::BUSINESS, SubscriptionStatus::ACTIVE);

        self::assertSame(Plan::BUSINESS, $subscription->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        self::assertSame(Plan::BUSINESS, $subscription->grantedPlan());
    }
}
