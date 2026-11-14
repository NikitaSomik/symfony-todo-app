<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Repository\SubscriptionRepository;
use App\Billing\Service\WorkspacePlanLimits;
use App\Workspace\Contract\MeetingLimits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkspacePlanLimitsTest extends TestCase
{
    private function limits(): WorkspacePlanLimits
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('planOf')->willReturn(Plan::FREE);
        $subscriptions->method('countOnFree')->willReturn(1);

        return new WorkspacePlanLimits($subscriptions);
    }

    #[Test]
    public function aMemberBelowTheLimitShouldPass(): void
    {
        $this->expectNotToPerformAssertions();

        $this->limits()->ensureCanAddMember(Uuid::v7(), 2);
    }

    #[Test]
    public function aMemberOverTheLimitShouldBeRefused(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $this->limits()->ensureCanAddMember(Uuid::v7(), 3);
    }

    #[Test]
    public function aSecondTeamWorkspaceOnFreeShouldBeRefused(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $this->limits()->ensureCanCreateTeamWorkspace([Uuid::v7()]);
    }

    #[Test]
    #[TestWith([Plan::FREE, 3, 40])]
    #[TestWith([Plan::TEAM, 10, 120])]
    #[TestWith([Plan::BUSINESS, 50, 240])]
    public function aMeetingShouldHoldThePlansMembersAndLastItsMinutes(Plan $plan, int $participants, int $minutes): void
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('planOf')->willReturn($plan);

        $limits = (new WorkspacePlanLimits($subscriptions))->findMeetingLimits(Uuid::v7());

        self::assertEquals(new MeetingLimits(participants: $participants, minutes: $minutes), $limits);
    }
}
