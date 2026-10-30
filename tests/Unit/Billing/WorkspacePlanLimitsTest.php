<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\LimitMode;
use App\Billing\Enum\Plan;
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
        $limits = $this->limits(LimitMode::OFF);

        self::assertTrue($limits->allowsAnotherMember(Uuid::v7(), 100));
        self::assertTrue($limits->allowsAnotherTeamWorkspace([Uuid::v7(), Uuid::v7()]));
    }

    #[Test]
    public function onShouldRefuseWhatIsOverTheLimits(): void
    {
        $limits = $this->limits(LimitMode::ON);

        self::assertFalse($limits->allowsAnotherMember(Uuid::v7(), 3));
        self::assertFalse($limits->allowsAnotherTeamWorkspace([Uuid::v7()]));
    }
}
