<?php

declare(strict_types=1);

namespace App\AuditLog\Service;

use App\AuditLog\Entity\AuditLog;
use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AuditLogLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed>|null $attributeChanges
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        AuditLogEntityType $entityType,
        string $entityId,
        ?int $actorId,
        AuditLogAction $action,
        string $message,
        ?array $attributeChanges = null,
        ?array $metadata = null,
    ): void {
        $this->em->persist(new AuditLog(
            entityType: $entityType,
            entityId: $entityId,
            actorId: $actorId,
            action: $action,
            message: $message,
            attributeChanges: $attributeChanges,
            metadata: $metadata,
            createdAt: $this->clock->now(),
        ));
    }
}
