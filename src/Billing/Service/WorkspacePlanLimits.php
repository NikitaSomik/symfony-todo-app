<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Repository\SubscriptionRepository;
use App\Workspace\Contract\PlanLimits;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspacePlanLimits implements PlanLimits
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
    ) {
    }

    public function ensureCanAddMember(Uuid $workspaceId, int $currentMembers): void
    {
        $plan = $this->subscriptions->planOf($workspaceId);

        if ($currentMembers >= $plan->memberLimit()) {
            throw PlanLimitExceededException::forMembers($plan, $currentMembers);
        }
    }

    public function ensureCanCreateTeamWorkspace(array $ownedTeamWorkspaceIds): void
    {
        $onFree = $this->subscriptions->countOnFree($ownedTeamWorkspaceIds);

        if ($onFree >= Plan::TEAM_WORKSPACES_ON_FREE) {
            throw PlanLimitExceededException::forTeamWorkspaces($onFree);
        }
    }
}
