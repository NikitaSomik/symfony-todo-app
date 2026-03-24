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

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->email(),
            'password' => '$2y$12$SnS/ZkwA82FyVBfi6dCeh.6daT9DLVETSLodKeC/GCWt/.lO4sXdC', // 'password'
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}
