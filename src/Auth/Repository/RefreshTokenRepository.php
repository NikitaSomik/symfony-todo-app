<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\RefreshToken;
use App\Auth\RefreshToken\RefreshTokenHash;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByToken(RefreshTokenHash $token, \DateTimeImmutable $now): ?RefreshToken
    {
        return $this->validByToken($token, $now)->getOneOrNullResult();
    }

    /**
     * Holds the token and its family until the transaction ends: a concurrent exchange in the
     * same session waits, then sees the token used or the session revoked.
     */
    public function lockValidByToken(RefreshTokenHash $token, \DateTimeImmutable $now): ?RefreshToken
    {
        return $this->validByToken($token, $now)->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

    public function deleteExpired(\DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /** @return Query<null, RefreshToken> */
    private function validByToken(RefreshTokenHash $token, \DateTimeImmutable $now): Query
    {
        return $this->createQueryBuilder('rt')
            ->addSelect('f')
            ->join('rt.family', 'f')
            ->where('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->andWhere('f.revokedAt IS NULL')
            ->andWhere('f.expiresAt > :now')
            ->setParameter('token', $token->value)
            ->setParameter('now', $now)
            ->getQuery();
    }
}
