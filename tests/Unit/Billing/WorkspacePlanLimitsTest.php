<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Repository\SubscriptionRepository;
use App\Billing\Service\WorkspacePlanLimits;
use PHPUnit\Framework\Attributes\Test;
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
}
