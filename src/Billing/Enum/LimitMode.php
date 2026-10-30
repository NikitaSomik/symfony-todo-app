<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum LimitMode: string
{
    case OFF = 'off';
    case ON = 'on';
}
