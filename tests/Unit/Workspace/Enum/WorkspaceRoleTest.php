<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workspace\Enum;

use App\Workspace\Contract\WorkspacePermission;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class WorkspaceRoleTest extends TestCase
{
    #[Test]
    #[TestWith([WorkspaceRole::OWNER, WorkspacePermission::VIEW, true])]
    #[TestWith([WorkspaceRole::MEMBER, WorkspacePermission::VIEW, true])]
    #[TestWith([WorkspaceRole::VIEWER, WorkspacePermission::VIEW, true])]
    #[TestWith([WorkspaceRole::OWNER, WorkspacePermission::MANAGE_WORKSPACE, true])]
    #[TestWith([WorkspaceRole::OWNER, WorkspacePermission::WORK_ON_TASKS, true])]
    #[TestWith([WorkspaceRole::OWNER, WorkspacePermission::DELETE_TASKS, true])]
    #[TestWith([WorkspaceRole::MEMBER, WorkspacePermission::MANAGE_WORKSPACE, false])]
    #[TestWith([WorkspaceRole::MEMBER, WorkspacePermission::WORK_ON_TASKS, true])]
    #[TestWith([WorkspaceRole::MEMBER, WorkspacePermission::DELETE_TASKS, false])]
    #[TestWith([WorkspaceRole::VIEWER, WorkspacePermission::MANAGE_WORKSPACE, false])]
    #[TestWith([WorkspaceRole::VIEWER, WorkspacePermission::WORK_ON_TASKS, false])]
    #[TestWith([WorkspaceRole::VIEWER, WorkspacePermission::DELETE_TASKS, false])]
    public function roleShouldCarryExactlyItsPermissions(WorkspaceRole $role, WorkspacePermission $permission, bool $allowed): void
    {
        self::assertSame($allowed, $role->can($permission));
    }
}
