<?php

declare(strict_types=1);

namespace App\Workspace\Repository;

use App\Workspace\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Workspace>
 */
final class WorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workspace::class);
    }

    /**
     * @return Workspace[]
     */
    public function findForMember(int $userId): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.members', 'm')
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('w.createdAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForMember(Uuid $id, int $userId): ?Workspace
    {
        return $this->createQueryBuilder('w')
            ->join('w.members', 'm')
            ->where('w.id = :id')
            ->andWhere('m.userId = :userId')
            ->setParameter('id', $id, UuidType::NAME)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
