<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspaceContext
{
    /**
     * @param list<WorkspacePermission> $permissions
     */
    public function __construct(
        public Uuid $workspaceId,
        private array $permissions,
    ) {
    }

    public function userCan(WorkspacePermission $permission): bool
    {
        return \in_array($permission, $this->permissions, true);
    }
}
