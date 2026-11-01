<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\LimitMode;
use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Repository\SubscriptionRepository;
use App\Billing\Service\WorkspacePlanLimits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkspacePlanLimitsTest extends TestCase
{
    private function limits(LimitMode $mode): WorkspacePlanLimits
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('planOf')->willReturn(Plan::FREE);

        return new WorkspacePlanLimits($subscriptions, $mode);
    }

    #[Test]
    public function offShouldAllowWhatTheLimitsWouldRefuse(): void
    {
        $this->expectNotToPerformAssertions();

        $limits = $this->limits(LimitMode::OFF);

        $limits->ensureCanAddMember(Uuid::v7(), 100);
        $limits->ensureCanCreateTeamWorkspace([Uuid::v7(), Uuid::v7()]);
    }

    #[Test]
    public function onShouldRefuseAMemberOverTheLimit(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $this->limits(LimitMode::ON)->ensureCanAddMember(Uuid::v7(), 3);
    }

    #[Test]
    public function onShouldRefuseASecondTeamWorkspaceOnFree(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $this->limits(LimitMode::ON)->ensureCanCreateTeamWorkspace([Uuid::v7()]);
    }
}
