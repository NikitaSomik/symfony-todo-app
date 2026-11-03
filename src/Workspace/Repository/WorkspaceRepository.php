<?php

declare(strict_types=1);

namespace App\Workspace\Repository;

use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
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

    public function countMembers(Uuid $workspaceId): int
    {
        return $this->getEntityManager()->getRepository(Membership::class)->count(['workspace' => $workspaceId]);
    }

    /**
     * @return list<Uuid>
     */
    public function findTeamWorkspaceIdsOwnedBy(int $userId): array
    {
        /** @var list<array{id: Uuid}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.id')
            ->join('w.members', 'm')
            ->where('m.userId = :userId')
            ->andWhere('m.role = :owner')
            ->andWhere('w.personal = false')
            ->setParameter('userId', $userId)
            ->setParameter('owner', WorkspaceRole::OWNER)
            ->getQuery()
            ->getResult();

        return array_column($rows, 'id');
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
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
    }

    private function findRole(Uuid $workspaceId, int $userId): ?WorkspaceRole
    {
        $membership = $this->getEntityManager()->getRepository(Membership::class)
            ->findOneBy(['workspace' => $workspaceId, 'userId' => $userId]);

        return null === $membership ? null : $membership->getRole();
    }

    public function permissionsOf(Uuid $workspaceId, int $userId): array
    {
        $role = $this->findRole($workspaceId, $userId);

        return null === $role ? [] : $role->permissions();
    }

    public function can(Uuid $workspaceId, int $userId, WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissionsOf($workspaceId, $userId), true);
    }
}
