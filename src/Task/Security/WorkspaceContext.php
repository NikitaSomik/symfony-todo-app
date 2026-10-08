<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Workspace\Contract\WorkspaceRole;
use Symfony\Component\Uid\Uuid;

final readonly class WorkspaceContext
{
    public function __construct(
        public Uuid $workspaceId,
        public WorkspaceRole $role,
    ) {
    }
}
