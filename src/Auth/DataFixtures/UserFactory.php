<?php

declare(strict_types=1);

namespace App\Auth\DataFixtures;

use App\Auth\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    /**
     * Hash of 'password' with the cheapest bcrypt cost: fixture users exist only in dev and test,
     * and tests verify this hash on every login, which is the slowest part of the suite.
     */
    private const string PASSWORD_HASH = '$2y$04$y0RQCGRHb8dxLRM6JG6DLecaG9cA1DNvkuiucA1aKTJ4to1JgFT6e';

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->email(),
            'password' => self::PASSWORD_HASH,
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
