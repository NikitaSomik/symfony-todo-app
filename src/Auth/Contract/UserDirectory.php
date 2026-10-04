<?php

declare(strict_types=1);

namespace App\Auth\Contract;

interface UserDirectory
{
    public function idByEmail(string $email): ?int;
}
