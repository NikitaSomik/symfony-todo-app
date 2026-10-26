<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\RefreshTokenFamily;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshTokenFamily>
 */
class RefreshTokenFamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshTokenFamily::class);
    }

    /** Their tokens go with them (ON DELETE CASCADE). */
    public function deleteEnded(\DateTimeImmutable $now): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.revokedAt IS NOT NULL')
            ->orWhere('f.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
