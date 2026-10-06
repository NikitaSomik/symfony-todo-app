<?php

declare(strict_types=1);

namespace App\Auth\Contract;

interface AuthenticatedUser
{
    public function id(): int;
}
