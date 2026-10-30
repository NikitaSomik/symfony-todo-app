<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

/** What a plan allows a workspace; whoever sells the plans answers. */
interface PlanLimits
{
    public function allowsAnotherMember(Uuid $workspaceId, int $members): bool;

    /** @param list<Uuid> $ownedTeamWorkspaceIds */
    public function allowsAnotherTeamWorkspace(array $ownedTeamWorkspaceIds): bool;
}
