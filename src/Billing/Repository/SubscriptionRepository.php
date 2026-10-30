<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
