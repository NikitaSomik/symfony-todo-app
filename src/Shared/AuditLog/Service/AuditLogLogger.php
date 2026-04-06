<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Service;

use App\Auth\Entity\User;
use App\Shared\AuditLog\Entity\AuditLog;
use App\Shared\AuditLog\Enum\AuditLogAction;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
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
     * @param array<string, mixed>|null $properties
     */
    public function log(
        AuditLogEntityType $entityType,
        string $entityId,
        ?User $user,
        AuditLogAction $action,
        string $message,
        ?array $attributeChanges = null,
        ?array $properties = null,
    ): void {
        $this->em->persist(new AuditLog(
            entityType: $entityType,
            entityId: $entityId,
            user: $user,
            action: $action,
            message: $message,
            attributeChanges: $attributeChanges,
            properties: $properties,
            createdAt: $this->clock->now(),
        ));
    }
}
