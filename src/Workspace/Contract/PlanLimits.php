<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

/** What a plan allows a workspace. Whoever sells the plans refuses by throwing, and says why. */
interface PlanLimits
{
    public function ensureCanAddMember(Uuid $workspaceId, int $currentMembers): void;

    /** @param list<Uuid> $ownedTeamWorkspaceIds */
    public function ensureCanCreateTeamWorkspace(array $ownedTeamWorkspaceIds): void;

    /** @return MeetingLimits|null null while a plan does not bound meetings */
    public function findMeetingLimits(Uuid $workspaceId): ?MeetingLimits;
}
