<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Contract\PlanLimits;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\DTO\MeetingAccess;
use Symfony\Component\Uid\Uuid;

final readonly class GetMeetingAccess
{
    /** User ids are a 32-bit column; PostgreSQL refuses to compare it with anything larger. */
    private const int LARGEST_USER_ID = 2_147_483_647;

    public function __construct(
        private WorkspaceAccess $workspaceAccess,
        private PlanLimits $planLimits,
    ) {
    }

    public function handle(Uuid $workspaceId, int $userId): MeetingAccess
    {
        if ($userId > self::LARGEST_USER_ID) {
            return MeetingAccess::ofSomeoneWhoIsNotAMember();
        }

        $permissions = $this->workspaceAccess->permissionsOf($workspaceId, $userId);

        // Someone who is not a member learns nothing about the workspace, not even its plan.
        if ([] === $permissions) {
            return MeetingAccess::ofSomeoneWhoIsNotAMember();
        }

        return new MeetingAccess($permissions, $this->planLimits->findMeetingLimits($workspaceId));
    }
}
