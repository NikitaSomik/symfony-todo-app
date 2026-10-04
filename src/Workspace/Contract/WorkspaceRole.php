<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
