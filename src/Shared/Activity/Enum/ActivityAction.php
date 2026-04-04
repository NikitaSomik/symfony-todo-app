<?php

declare(strict_types=1);

namespace App\Shared\Activity\Enum;

enum ActivityAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
}
