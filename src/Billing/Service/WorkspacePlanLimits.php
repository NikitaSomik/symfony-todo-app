<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\LimitMode;
use App\Billing\Enum\Plan;
use App\Billing\Exception\PlanLimitExceededException;
use App\Billing\Repository\SubscriptionRepository;
use App\Workspace\Contract\PlanLimits;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspacePlanLimits implements PlanLimits
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private LimitMode $mode,
    ) {
    }

    public function ensureCanAddMember(Uuid $workspaceId, int $currentMembers): void
    {
        if (LimitMode::OFF === $this->mode) {
            return;
        }

        $plan = $this->subscriptions->planOf($workspaceId);

        if ($currentMembers >= $plan->memberLimit()) {
            throw PlanLimitExceededException::forMembers($plan, $currentMembers);
        }
    }

    public function ensureCanCreateTeamWorkspace(array $ownedTeamWorkspaceIds): void
    {
        if (LimitMode::OFF === $this->mode) {
            return;
        }

        $onFree = array_filter(
            $ownedTeamWorkspaceIds,
            fn (Uuid $workspaceId): bool => Plan::FREE === $this->subscriptions->planOf($workspaceId),
        );

        if (count($onFree) >= Plan::TEAM_WORKSPACES_ON_FREE) {
            throw PlanLimitExceededException::forTeamWorkspaces(count($onFree));
        }
    }
}
