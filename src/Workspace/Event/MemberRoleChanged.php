<?php

declare(strict_types=1);

namespace App\Workspace\Event;

use App\Workspace\Enum\WorkspaceRole;

final readonly class MemberRoleChanged
{
    public function __construct(
        public string $workspaceId,
        public string $workspaceName,
        public int $userId,
        public WorkspaceRole $previousRole,
        public WorkspaceRole $role,
        public int $actorId,
    ) {
    }
}
