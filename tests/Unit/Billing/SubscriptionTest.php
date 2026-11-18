<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Entity\Payer;
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
        return new Subscription(stripeSubscriptionId: 'sub_test', payer: new Payer(workspaceId: Uuid::v7(), stripeCustomerId: 'cus_test'), plan: $plan, status: $status, updatedAt: new \DateTimeImmutable('2026-11-16 10:00:00+00:00'));
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
    public function aCanceledSubscriptionShouldSayItIsCanceled(): void
    {
        self::assertTrue($this->subscription(Plan::BUSINESS, SubscriptionStatus::CANCELED)->isCanceled());
    }

    #[Test]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::ACTIVE])]
    #[TestWith([SubscriptionStatus::GRACE])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    public function aSubscriptionThatIsNotCanceledShouldSaySo(SubscriptionStatus $status): void
    {
        self::assertFalse($this->subscription(Plan::BUSINESS, $status)->isCanceled());
    }

    #[Test]
    public function aSyncShouldTakeThePlanAndTheStatusStripeAnswered(): void
    {
        $subscription = $this->subscription(Plan::TEAM, SubscriptionStatus::PENDING);

        $subscription->sync(Plan::BUSINESS, SubscriptionStatus::ACTIVE, at: new \DateTimeImmutable('2026-11-16 12:00:00+00:00'));

        self::assertSame(Plan::BUSINESS, $subscription->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        self::assertSame(Plan::BUSINESS, $subscription->grantedPlan());
        self::assertEquals(new \DateTimeImmutable('2026-11-16 12:00:00+00:00'), $subscription->getUpdatedAt());
    }
}
