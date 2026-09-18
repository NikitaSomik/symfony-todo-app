<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\RefreshToken;
use App\Auth\Entity\User;
use App\Auth\RefreshToken\IssuedRefreshToken;
use App\Auth\RefreshToken\RefreshTokenHash;
use Doctrine\ORM\EntityManagerInterface;

final class IssueRefreshToken
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function handle(User $user): IssuedRefreshToken
    {
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', $this->refreshTokenTtl));

        $this->em->persist(new RefreshToken(RefreshTokenHash::fromPlain($plainToken), $user, $expiresAt));

        return new IssuedRefreshToken($plainToken, $expiresAt);
    }
}
