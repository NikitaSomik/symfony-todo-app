<?php

declare(strict_types=1);

namespace App\Auth\Contract;

interface UserDirectory
{
    public function findIdByEmail(string $email): ?int;

    public function reference(int $id): UserReference;
}
