<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
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
        $subscription = $this->find($workspaceId);

        return null === $subscription ? Plan::FREE : $subscription->getPlan();
    }

    /**
     * @param list<Uuid> $workspaceIds
     */
    public function countOnFree(array $workspaceIds): int
    {
        if ([] === $workspaceIds) {
            return 0;
        }

        /** @var int<0, max> $paid */
        $paid = $this->createQueryBuilder('s')
            ->select('COUNT(s.workspaceId)')
            ->where('s.workspaceId IN (:ids)')
            ->andWhere('s.plan != :free')
            ->setParameter('ids', array_map(static fn (Uuid $id): string => $id->toRfc4122(), $workspaceIds), ArrayParameterType::STRING)
            ->setParameter('free', Plan::FREE)
            ->getQuery()
            ->getSingleScalarResult();

        return count($workspaceIds) - $paid;
    }
}
