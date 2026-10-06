<?php

declare(strict_types=1);

namespace App\Workspace\Event;

use App\Workspace\Contract\WorkspaceRole;

final readonly class MemberLeft
{
    public function __construct(
        public string $workspaceId,
        public string $workspaceName,
        public int $userId,
        public WorkspaceRole $role,
    ) {
    }
}
