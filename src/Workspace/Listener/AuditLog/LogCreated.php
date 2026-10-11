<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\WorkspaceCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogCreated
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(WorkspaceCreated $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->actorId,
            action: AuditLogAction::CREATED,
            message: sprintf('Created workspace "%s"', $event->name),
            metadata: ['entity_data' => ['name' => $event->name]],
        );
    }
}
