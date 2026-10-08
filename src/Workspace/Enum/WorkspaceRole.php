<?php

declare(strict_types=1);

namespace App\Workspace\Enum;

use App\Workspace\Contract\WorkspacePermission;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /** @return list<WorkspacePermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::OWNER => [WorkspacePermission::VIEW, WorkspacePermission::MANAGE_WORKSPACE, WorkspacePermission::WORK_ON_TASKS, WorkspacePermission::DELETE_TASKS],
            self::MEMBER => [WorkspacePermission::VIEW, WorkspacePermission::WORK_ON_TASKS],
            self::VIEWER => [WorkspacePermission::VIEW],
        };
    }

    public function can(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
