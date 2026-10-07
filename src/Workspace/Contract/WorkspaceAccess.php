<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

interface WorkspaceAccess
{
    /** Null when the user is not a member, which is also what a missing workspace answers. */
    public function roleOf(Uuid $workspaceId, int $userId): ?WorkspaceRole;

    public function reference(Uuid $workspaceId): WorkspaceReference;
}
