<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workspace\Service;

use App\Workspace\Contract\MeetingLimits;
use App\Workspace\Contract\PlanLimits;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use App\Workspace\DTO\MeetingAccess;
use App\Workspace\Service\GetMeetingAccess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetMeetingAccessTest extends TestCase
{
    #[Test]
    public function aMemberShouldGetTheirPermissionsAndTheLimitsOfThePlan(): void
    {
        $limits = new MeetingLimits(participants: 10, minutes: 120);
        $workspaceAccess = $this->createStub(WorkspaceAccess::class);
        $workspaceAccess->method('permissionsOf')->willReturn([WorkspacePermission::VIEW]);
        $planLimits = $this->createStub(PlanLimits::class);
        $planLimits->method('findMeetingLimits')->willReturn($limits);

        $access = (new GetMeetingAccess($workspaceAccess, $planLimits))->handle(Uuid::v7(), 7);

        self::assertEquals(new MeetingAccess([WorkspacePermission::VIEW], $limits), $access);
    }

    #[Test]
    public function someoneWhoIsNotAMemberShouldNotLearnThePlansLimits(): void
    {
        $workspaceAccess = $this->createStub(WorkspaceAccess::class);
        $workspaceAccess->method('permissionsOf')->willReturn([]);
        $planLimits = $this->createMock(PlanLimits::class);
        $planLimits->expects(self::never())->method('findMeetingLimits');

        $access = (new GetMeetingAccess($workspaceAccess, $planLimits))->handle(Uuid::v7(), 7);

        self::assertEquals(MeetingAccess::ofSomeoneWhoIsNotAMember(), $access);
    }

    #[Test]
    public function aUserIdNoUserCanHaveShouldNotReachTheDatabase(): void
    {
        $workspaceAccess = $this->createMock(WorkspaceAccess::class);
        $workspaceAccess->expects(self::never())->method('permissionsOf');

        $access = (new GetMeetingAccess($workspaceAccess, $this->createStub(PlanLimits::class)))->handle(Uuid::v7(), 2_147_483_648);

        self::assertEquals(MeetingAccess::ofSomeoneWhoIsNotAMember(), $access);
    }
}
