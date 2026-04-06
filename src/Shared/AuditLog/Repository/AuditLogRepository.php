<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Repository;

use App\Shared\AuditLog\Entity\AuditLog;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return AuditLog[]
     */
    public function findForEntity(AuditLogEntityType $entityType, string $entityId): array
    {
        return $this->findBy(
            ['entityType' => $entityType, 'entityId' => $entityId],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        );
    }
}
