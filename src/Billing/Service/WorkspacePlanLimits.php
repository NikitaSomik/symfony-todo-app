<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Query\WorkspacePlans;
use App\Workspace\Contract\MeetingLimits;
use App\Workspace\Contract\PlanLimits;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspacePlanLimits implements PlanLimits
{
    public function __construct(
        private WorkspacePlans $plans,
    ) {
    }

    public function ensureCanAddMember(Uuid $workspaceId, int $currentMembers): void
    {
        $plan = $this->plans->planOf($workspaceId);

        if ($currentMembers >= $plan->memberLimit()) {
            throw PlanLimitExceededException::forMembers($plan, $currentMembers);
        }
    }

    public function ensureCanCreateTeamWorkspace(array $ownedTeamWorkspaceIds): void
    {
        $onFree = $this->plans->countOnFree($ownedTeamWorkspaceIds);

        if ($onFree >= Plan::TEAM_WORKSPACES_ON_FREE) {
            throw PlanLimitExceededException::forTeamWorkspaces($onFree);
        }
    }

    public function findMeetingLimits(Uuid $workspaceId): MeetingLimits
    {
        $plan = $this->plans->planOf($workspaceId);

        return new MeetingLimits(participants: $plan->memberLimit(), minutes: $plan->meetingMinutes());
    }
}
