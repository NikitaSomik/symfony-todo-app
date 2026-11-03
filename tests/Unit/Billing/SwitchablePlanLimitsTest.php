<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\LimitMode;
use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Service\SwitchablePlanLimits;
use App\Workspace\Contract\PlanLimits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class SwitchablePlanLimitsTest extends TestCase
{
    private function refusingLimits(): PlanLimits
    {
        $limits = $this->createStub(PlanLimits::class);
        $limits->method('ensureCanAddMember')->willThrowException(PlanLimitExceededException::forMembers(Plan::FREE, 3));
        $limits->method('ensureCanCreateTeamWorkspace')->willThrowException(PlanLimitExceededException::forTeamWorkspaces(1));

        return $limits;
    }

    #[Test]
    public function offShouldNotAskTheRules(): void
    {
        $limits = $this->createMock(PlanLimits::class);
        $limits->expects(self::never())->method('ensureCanAddMember');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        (new SwitchablePlanLimits($limits, LimitMode::OFF, $logger))->ensureCanAddMember(Uuid::v7(), 3);
    }

    #[Test]
    public function shadowShouldRecordARefusalAndLetItPass(): void
    {
        $workspaceId = Uuid::v7();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'Plan limit reached in shadow mode.',
            ['workspace_id' => $workspaceId->toRfc4122(), 'plan' => 'free', 'limit' => 3, 'used' => 3, 'next_plan' => 'team'],
        );

        (new SwitchablePlanLimits($this->refusingLimits(), LimitMode::SHADOW, $logger))->ensureCanAddMember($workspaceId, 3);
    }

    #[Test]
    public function shadowShouldRecordNothingWhenTheRulesPass(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        (new SwitchablePlanLimits($this->createStub(PlanLimits::class), LimitMode::SHADOW, $logger))->ensureCanCreateTeamWorkspace([]);
    }

    #[Test]
    public function onShouldRefuse(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        (new SwitchablePlanLimits($this->refusingLimits(), LimitMode::ON, $this->createStub(LoggerInterface::class)))->ensureCanCreateTeamWorkspace([Uuid::v7()]);
    }
}
