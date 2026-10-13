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
            'family' => RefreshTokenFamilyFactory::new(),
            'at' => new \DateTimeImmutable(),
            'expiresAt' => new \DateTimeImmutable('+30 days'),
        ];
    }

    protected function initialize(): static
    {
        // A token for a user starts a session of that user, as a sign-in does.
        return $this->beforeInstantiate(static function (array $attributes): array {
            if (isset($attributes['user'])) {
                $attributes['family'] = RefreshTokenFamilyFactory::new(['user' => $attributes['user']]);
                unset($attributes['user']);
            }

            return $attributes;
        });
    }

    public function used(\DateTimeImmutable $at = new \DateTimeImmutable()): static
    {
        return $this->afterInstantiate(static fn (RefreshToken $token) => $token->markUsed($at));
    }

    public function expired(): static
    {
        return $this->with(['expiresAt' => new \DateTimeImmutable('-1 day')]);
    }
}
