<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class MembershipChanged
{
    /**
     * @param list<WorkspacePermission> $permissions
     */
    private function __construct(
        public Uuid $workspaceId,
        public int $userId,
        public array $permissions,
        public int $actorId,
    ) {
    }

    /**
     * @param list<WorkspacePermission> $permissions what the user may do from now on
     */
    public static function roleChanged(Uuid $workspaceId, int $userId, array $permissions, int $actorId): self
    {
        return new self($workspaceId, $userId, $permissions, $actorId);
    }

    public static function ended(Uuid $workspaceId, int $userId, int $actorId): self
    {
        return new self($workspaceId, $userId, [], $actorId);
    }

    public function userCan(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
