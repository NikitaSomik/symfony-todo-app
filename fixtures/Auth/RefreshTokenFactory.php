<?php

declare(strict_types=1);

namespace App\Fixtures\Auth;

use App\Auth\Entity\RefreshToken;
use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RefreshToken>
 */
final class RefreshTokenFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RefreshToken::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'token' => RefreshTokenHash::fromPlain((new RandomRefreshTokenGenerator())->generate()),
            'user' => UserFactory::new(),
            'expiresAt' => new \DateTimeImmutable('+30 days'),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public function expired(): static
    {
        return $this->with(['expiresAt' => new \DateTimeImmutable('-1 day')]);
    }
}
