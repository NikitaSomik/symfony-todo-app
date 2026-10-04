<?php

declare(strict_types=1);

namespace App\AuditLog\Enum;

enum AuditLogAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
}
