<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class MemberPermissionsChanged
{
    /**
     * @param list<WorkspacePermission> $permissions what the member may do from now on
     */
    public function __construct(
        public Uuid $workspaceId,
        public int $userId,
        public array $permissions,
        public int $actorId,
    ) {
    }

    public function memberCan(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
