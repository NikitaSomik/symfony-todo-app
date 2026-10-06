<?php

declare(strict_types=1);

namespace App\Auth\Contract;

interface UserDirectory
{
    public function findIdByEmail(string $email): ?int;

    /** Stands for the user with this id in a relation; the user is not loaded. */
    public function reference(int $id): AuthenticatedUser;
}
