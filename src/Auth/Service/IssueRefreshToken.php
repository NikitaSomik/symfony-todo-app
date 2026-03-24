<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\RefreshToken;
use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class IssueRefreshToken
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function handle(User $user): RefreshToken
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', $this->refreshTokenTtl));

        $refreshToken = new RefreshToken($token, $user, $expiresAt);

        $this->em->persist($refreshToken);

        return $refreshToken;
    }
}
