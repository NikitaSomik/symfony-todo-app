<?php

declare(strict_types=1);

namespace App\Shared\Activity\Repository;

use App\Shared\Activity\Entity\ActivityLog;
use App\Shared\Activity\Enum\ActivityEntityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
final class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findForEntity(ActivityEntityType $entityType, string $entityId): array
    {
        return $this->findBy(
            ['entityType' => $entityType, 'entityId' => $entityId],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        );
    }
}
