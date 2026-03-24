<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\RefreshToken;
use App\Auth\Exception\InvalidRefreshTokenException;
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

    /** @return array{jwt: string, refreshToken: RefreshToken} */
    public function handle(string $token): array
    {
        return $this->em->wrapInTransaction(function () use ($token): array {
            $refreshToken = $this->refreshTokenRepository->findValidByToken($token);

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
