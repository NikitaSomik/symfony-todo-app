<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Enum;

enum AuditLogEntityType: string
{
    case TASK = 'task';

    /**
     * The JSON:API type the audited entity is exposed under.
     */
    public function resourceType(): string
    {
        return match ($this) {
            self::TASK => 'tasks',
        };
    }
}
