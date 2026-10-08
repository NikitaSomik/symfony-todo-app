<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class MembershipChanged
{
    /**
     * @param list<WorkspacePermission> $permissions what the user may do now; empty when they are no longer a member
     */
    public function __construct(
        public Uuid $workspaceId,
        public int $userId,
        public array $permissions,
        public int $actorId,
    ) {
    }

    public function userCan(WorkspacePermission $permission): bool
    {
        return \in_array($permission, $this->permissions, true);
    }
}
