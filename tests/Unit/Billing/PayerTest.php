<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Entity\Payer;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Exception\SecondSubscriptionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PayerTest extends TestCase
{
    private Payer $payer;

    protected function setUp(): void
    {
        $this->payer = new Payer(workspaceId: Uuid::fromString('019a8f3e-0000-7000-8000-000000000001'), stripeCustomerId: 'cus_workspace');
    }

    private function sync(string $subscriptionId, SubscriptionStatus $status, Plan $plan = Plan::TEAM, string $at = '2026-11-18 10:00:00+00:00'): void
    {
        $this->payer->syncSubscription($subscriptionId, $plan, $status, new \DateTimeImmutable($at));
    }

    /** @return array<string, array{Plan, SubscriptionStatus}> */
    private function subscriptions(): array
    {
        $byId = [];
        foreach ($this->payer->getSubscriptions() as $subscription) {
            $byId[$subscription->getStripeSubscriptionId()] = [$subscription->getPlan(), $subscription->getStatus()];
        }
        ksort($byId);

        return $byId;
    }

    #[Test]
    public function aFirstSubscriptionShouldBeAddedAsStripeAnswered(): void
    {
        $this->sync('sub_first', SubscriptionStatus::PENDING, Plan::BUSINESS);

        self::assertSame(['sub_first' => [Plan::BUSINESS, SubscriptionStatus::PENDING]], $this->subscriptions());
    }

    #[Test]
    public function aKnownSubscriptionShouldFollowStripesAnswerAndKeepOneRecord(): void
    {
        $this->sync('sub_first', SubscriptionStatus::PENDING, Plan::TEAM, at: '2026-11-18 10:00:00+00:00');

        $this->sync('sub_first', SubscriptionStatus::ACTIVE, Plan::BUSINESS, at: '2026-11-18 10:05:00+00:00');

        self::assertSame(['sub_first' => [Plan::BUSINESS, SubscriptionStatus::ACTIVE]], $this->subscriptions());
        self::assertEquals(new \DateTimeImmutable('2026-11-18 10:05:00+00:00'), $this->payer->getSubscriptions()[0]->getUpdatedAt());
    }

    #[Test]
    public function aNewSubscriptionAfterACancelledOneShouldBeAddedBesideIt(): void
    {
        $this->sync('sub_first', SubscriptionStatus::CANCELED);

        $this->sync('sub_second', SubscriptionStatus::ACTIVE, Plan::BUSINESS);

        self::assertSame([
            'sub_first' => [Plan::TEAM, SubscriptionStatus::CANCELED],
            'sub_second' => [Plan::BUSINESS, SubscriptionStatus::ACTIVE],
        ], $this->subscriptions());
    }

    #[Test]
    #[TestWith([SubscriptionStatus::ACTIVE])]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::GRACE])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    public function aSecondSubscriptionBesideOneNotCancelledShouldBeRefusedWithBothIds(SubscriptionStatus $first): void
    {
        $this->sync('sub_first', $first);

        try {
            $this->sync('sub_second', SubscriptionStatus::ACTIVE, Plan::BUSINESS);
            self::fail('A second subscription beside one not cancelled was added.');
        } catch (SecondSubscriptionException $refusal) {
            self::assertSame('019a8f3e-0000-7000-8000-000000000001', $refusal->workspaceId->toRfc4122());
            self::assertSame('sub_second', $refusal->subscriptionId);
            self::assertSame('sub_first', $refusal->currentSubscriptionId);
        }

        self::assertSame(['sub_first' => [Plan::TEAM, $first]], $this->subscriptions());
    }
}
