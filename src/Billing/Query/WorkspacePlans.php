<?php

declare(strict_types=1);

namespace App\Billing\Query;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Reads plans straight from the subscriptions; writes go through their payer (0038). Not final, so that a unit test can stub it. */
readonly class WorkspacePlans
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
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
    private function findNotCanceledOf(Uuid $workspaceId): ?Subscription
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(Subscription::class, 's')
            ->where('IDENTITY(s.payer) = :workspaceId')
            ->andWhere('s.status != :canceled')
            ->setParameter('workspaceId', $workspaceId, UuidType::NAME)
            ->setParameter('canceled', SubscriptionStatus::CANCELED)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
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
        $paid = $this->em->createQueryBuilder()
            ->from(Subscription::class, 's')
            ->select('COUNT(DISTINCT IDENTITY(s.payer))')
            ->where('IDENTITY(s.payer) IN (:ids)')
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
