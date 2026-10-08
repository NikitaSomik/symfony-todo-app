<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class MembershipChanged
{
    /**
     * @param WorkspaceRole|null $role null when the user is no longer a member
     */
    public function __construct(
        public Uuid $workspaceId,
        public int $userId,
        public ?WorkspaceRole $role,
        public int $actorId,
    ) {
    }
}
