<?php

declare(strict_types=1);

namespace App\Auth\Contract;

interface UserReference
{
    public function id(): int;
}
