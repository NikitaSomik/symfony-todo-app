<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Workspace\Contract\WorkspaceRole;
use Symfony\Component\Uid\Uuid;

/** The workspace named in the URL, as the current user stands in it. */
final readonly class WorkspaceMembership
{
    public function __construct(
        public Uuid $workspaceId,
        public WorkspaceRole $role,
    ) {
    }
}
