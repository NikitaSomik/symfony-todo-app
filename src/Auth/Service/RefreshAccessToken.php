<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\InvalidRefreshTokenException;
use App\Auth\RefreshToken\IssuedRefreshToken;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class RefreshAccessToken
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly IssueRefreshToken $issueRefreshToken,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array{jwt: string, refreshToken: IssuedRefreshToken} */
    public function handle(string $token): array
    {
        return $this->em->wrapInTransaction(function () use ($token): array {
            $refreshToken = $this->refreshTokenRepository->findValidByToken(RefreshTokenHash::fromPlain($token));

            if (null === $refreshToken) {
                throw new InvalidRefreshTokenException();
            }

            $user = $refreshToken->getUser();

            $this->em->remove($refreshToken);

            $newRefreshToken = $this->issueRefreshToken->handle($user);
            $this->em->flush();

            return [
                'jwt' => $this->jwtManager->create($user),
                'refreshToken' => $newRefreshToken,
            ];
        });
    }
}
