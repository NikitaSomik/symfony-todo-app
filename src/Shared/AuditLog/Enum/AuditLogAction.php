<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Enum;

enum AuditLogAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
}
