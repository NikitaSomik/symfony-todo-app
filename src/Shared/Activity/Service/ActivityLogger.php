<?php

declare(strict_types=1);

namespace App\Shared\Activity\Service;

use App\Auth\Entity\User;
use App\Shared\Activity\Entity\ActivityLog;
use App\Shared\Activity\Enum\ActivityAction;
use App\Shared\Activity\Enum\ActivityEntityType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ActivityLogger
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
        ActivityEntityType $entityType,
        int $entityId,
        ?User $user,
        ActivityAction $action,
        string $message,
        ?array $attributeChanges = null,
        ?array $properties = null,
    ): void {
        $this->em->persist(new ActivityLog(
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
