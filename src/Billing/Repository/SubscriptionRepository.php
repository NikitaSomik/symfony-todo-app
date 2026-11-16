<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function planOf(Uuid $workspaceId): Plan
    {
        $subscription = $this->findNotCanceledOf($workspaceId);

        if (null === $subscription) {
            return Plan::FREE;
        }

        return $subscription->grantedPlan();
    }

    /** A workspace has at most one: the partial unique index on workspace_id holds it. */
    public function findNotCanceledOf(Uuid $workspaceId): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.workspaceId = :workspaceId')
            ->andWhere('s.status != :canceled')
            ->setParameter('workspaceId', $workspaceId, UuidType::NAME)
            ->setParameter('canceled', SubscriptionStatus::CANCELED)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
    }

    /**
     * @param list<Uuid> $workspaceIds
     */
    public function countWorkspacesOnFree(array $workspaceIds): int
    {
        if ([] === $workspaceIds) {
            return 0;
        }

        /** @var int<0, max> $paid */
        $paid = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.workspaceId)')
            ->where('s.workspaceId IN (:ids)')
            ->andWhere('s.status IN (:grantingPlan)')
            ->andWhere('s.plan != :free')
            ->setParameter('ids', array_map(static fn (Uuid $id): string => $id->toRfc4122(), $workspaceIds), ArrayParameterType::STRING)
            ->setParameter('grantingPlan', array_map(static fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::grantingPlan()), ArrayParameterType::STRING)
            ->setParameter('free', Plan::FREE)
            ->getQuery()
            ->getSingleScalarResult();

        return count($workspaceIds) - $paid;
    }
}
