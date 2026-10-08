<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

interface WorkspaceAccess
{
    /**
     * @return list<WorkspacePermission> empty for someone who is not a member, and for a workspace that does not exist
     */
    public function permissionsOf(Uuid $workspaceId, int $userId): array;

    public function can(Uuid $workspaceId, int $userId, WorkspacePermission $permission): bool;

    public function reference(Uuid $workspaceId): WorkspaceReference;
}
