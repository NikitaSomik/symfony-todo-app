<?php

declare(strict_types=1);

namespace App\Fixtures\Auth;

use App\Auth\Entity\RefreshTokenFamily;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RefreshTokenFamily>
 */
final class RefreshTokenFamilyFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RefreshTokenFamily::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => Uuid::v7(),
            'user' => UserFactory::new(),
            'at' => new \DateTimeImmutable(),
            'expiresAt' => new \DateTimeImmutable('+90 days'),
        ];
    }

    public function revoked(): static
    {
        return $this->afterInstantiate(static fn (RefreshTokenFamily $family) => $family->revoke(new \DateTimeImmutable()));
    }

    public function expired(): static
    {
        return $this->with(['expiresAt' => new \DateTimeImmutable('-1 day')]);
    }
}
