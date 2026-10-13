<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\RefreshToken;
use App\Auth\Entity\RefreshTokenFamily;
use App\Auth\Entity\User;
use App\Auth\RefreshToken\IssuedRefreshToken;
use App\Auth\RefreshToken\RefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class IssueRefreshToken
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenGenerator $refreshTokenGenerator,
        private readonly ClockInterface $clock,
        private readonly int $refreshTokenTtl,
        private readonly int $familyTtl,
    ) {
    }

    public function handle(User $user): IssuedRefreshToken
    {
        $now = $this->clock->now();
        $family = new RefreshTokenFamily(Uuid::v7(), $user, $now, $now->modify(sprintf('+%d days', $this->familyTtl)));
        $this->em->persist($family);

        return $this->inFamily($family);
    }

    public function inFamily(RefreshTokenFamily $family): IssuedRefreshToken
    {
        $now = $this->clock->now();
        $plainToken = $this->refreshTokenGenerator->generate();
        $expiresAt = min($now->modify(sprintf('+%d days', $this->refreshTokenTtl)), $family->getExpiresAt());

        $this->em->persist(new RefreshToken(RefreshTokenHash::fromPlain($plainToken), $family, $now, $expiresAt));

        return new IssuedRefreshToken($plainToken, $expiresAt);
    }
}
