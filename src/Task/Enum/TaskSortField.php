<?php

declare(strict_types=1);

namespace App\Task\Enum;

enum TaskSortField: string
{
    case CREATED_AT = 'created_at';
    case STATUS = 'status';
    case DUE_DATE = 'due_date';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
