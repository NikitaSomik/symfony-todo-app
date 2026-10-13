<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\InvalidRefreshTokenException;
use App\Auth\Exception\RefreshTokenReusedException;
use App\Auth\RefreshToken\IssuedRefreshToken;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Rotation with reuse detection (RFC 9700, ADR 0025).
 */
final class RefreshAccessToken
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly IssueRefreshToken $issueRefreshToken,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly int $reuseWindowSeconds,
    ) {
    }

    /** @return array{jwt: string, refreshToken: IssuedRefreshToken} */
    public function handle(string $token): array
    {
        $outcome = $this->em->wrapInTransaction(fn (): array|InvalidRefreshTokenException => $this->exchange(RefreshTokenHash::fromPlain($token)));

        if ($outcome instanceof InvalidRefreshTokenException) {
            throw $outcome;
        }

        return $outcome;
    }

    /**
     * A refusal is returned, not thrown: throwing would roll back the revocation of a family.
     *
     * @return array{jwt: string, refreshToken: IssuedRefreshToken}|InvalidRefreshTokenException
     */
    private function exchange(RefreshTokenHash $hash): array|InvalidRefreshTokenException
    {
        $now = $this->clock->now();
        $refreshToken = $this->refreshTokenRepository->lockValidByToken($hash, $now);

        if (null === $refreshToken) {
            return new InvalidRefreshTokenException();
        }

        $family = $refreshToken->getFamily();

        if ($refreshToken->isReplayed($now, $this->reuseWindowSeconds)) {
            $family->revoke($now);

            return new RefreshTokenReusedException((int) $family->getUser()->getId(), $family->getId());
        }

        $refreshToken->markUsed($now);

        return [
            'jwt' => $this->jwtManager->create($family->getUser()),
            'refreshToken' => $this->issueRefreshToken->inFamily($family),
        ];
    }
}
