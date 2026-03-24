<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\RefreshToken;
use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class IssueRefreshToken
{
    private const int TTL_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(User $user): RefreshToken
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', self::TTL_DAYS));

        $refreshToken = new RefreshToken($token, $user, $expiresAt);

        $this->em->persist($refreshToken);

        return $refreshToken;
    }
}
