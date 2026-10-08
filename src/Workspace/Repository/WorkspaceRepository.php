<?php

declare(strict_types=1);

namespace App\Workspace\Repository;

use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use App\Workspace\Contract\WorkspaceReference;
use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Workspace>
 */
final class WorkspaceRepository extends ServiceEntityRepository implements WorkspaceAccess
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
            ->where('IDENTITY(m.user) = :userId')
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
            ->andWhere('IDENTITY(m.user) = :userId')
            ->setParameter('id', $id, UuidType::NAME)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function findRole(Uuid $workspaceId, int $userId): ?WorkspaceRole
    {
        $role = $this->getEntityManager()->createQueryBuilder()
            ->select('m.role')
            ->from(Membership::class, 'm')
            ->where('IDENTITY(m.workspace) = :workspaceId')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->setParameter('workspaceId', $workspaceId, UuidType::NAME)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return null === $role ? null : WorkspaceRole::from($role);
    }

    public function permissionsOf(Uuid $workspaceId, int $userId): array
    {
        $role = $this->findRole($workspaceId, $userId);

        return null === $role ? [] : $role->permissions();
    }

    public function can(Uuid $workspaceId, int $userId, WorkspacePermission $permission): bool
    {
        return \in_array($permission, $this->permissionsOf($workspaceId, $userId), true);
    }

    public function reference(Uuid $workspaceId): WorkspaceReference
    {
        /** @var Workspace $workspace */
        $workspace = $this->getEntityManager()->getReference(Workspace::class, $workspaceId);

        return $workspace;
    }
}
