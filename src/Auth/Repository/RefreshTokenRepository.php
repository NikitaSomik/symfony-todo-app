<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\RefreshToken;
use App\Auth\Entity\User;
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

    public function findValidByToken(RefreshTokenHash $token): ?RefreshToken
    {
        return $this->validByToken($token)->getOneOrNullResult();
    }

    /** Holds the row until the transaction ends: a concurrent exchange of the same token waits, then finds it gone. */
    public function lockValidByToken(RefreshTokenHash $token): ?RefreshToken
    {
        return $this->validByToken($token)->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

    public function deleteAllForUser(User $user): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /** @return Query<null, RefreshToken> */
    private function validByToken(RefreshTokenHash $token): Query
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token->value)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();
    }
}
