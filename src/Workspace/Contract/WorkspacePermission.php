<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

enum WorkspacePermission: string
{
    case VIEW = 'view';
    case MANAGE_WORKSPACE = 'manage_workspace';
    case WORK_ON_TASKS = 'work_on_tasks';
    case DELETE_TASKS = 'delete_tasks';
}
