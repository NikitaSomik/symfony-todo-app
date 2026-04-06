<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Enum;

enum AuditLogEntityType: string
{
    case TASK = 'task';
}
