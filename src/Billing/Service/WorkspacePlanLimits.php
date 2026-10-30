<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Enum\LimitMode;
use App\Billing\Enum\Plan;
use App\Billing\Repository\SubscriptionRepository;
use App\Workspace\Contract\PlanLimits;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspacePlanLimits implements PlanLimits
{
    private const int TEAM_WORKSPACES_ON_FREE = 1;

    public function __construct(
        private SubscriptionRepository $subscriptions,
        private LimitMode $mode,
    ) {
    }

    public function allowsAnotherMember(Uuid $workspaceId, int $members): bool
    {
        if (LimitMode::OFF === $this->mode) {
            return true;
        }

        return $members < $this->subscriptions->planOf($workspaceId)->memberLimit();
    }

    public function allowsAnotherTeamWorkspace(array $ownedTeamWorkspaceIds): bool
    {
        if (LimitMode::OFF === $this->mode) {
            return true;
        }

        $onFree = array_filter(
            $ownedTeamWorkspaceIds,
            fn (Uuid $workspaceId): bool => Plan::FREE === $this->subscriptions->planOf($workspaceId),
        );

        return count($onFree) < self::TEAM_WORKSPACES_ON_FREE;
    }
}
