<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

interface WorkspaceAccess
{
    /** Null when the user is not a member, which is also what a missing workspace answers. */
    public function roleOf(Uuid $workspaceId, int $userId): ?WorkspaceRole;

    /** Stands for the workspace with this id in a relation; the workspace is not loaded. */
    public function reference(Uuid $workspaceId): WorkspaceReference;

    /** @return list<Uuid> */
    public function workspaceIdsOf(int $userId): array;
}
