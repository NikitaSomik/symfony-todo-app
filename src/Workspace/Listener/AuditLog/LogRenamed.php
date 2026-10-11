<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\WorkspaceRenamed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogRenamed
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(WorkspaceRenamed $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->actorId,
            action: AuditLogAction::UPDATED,
            message: sprintf('Renamed workspace "%s" to "%s"', $event->previousName, $event->name),
            attributeChanges: ['old' => ['name' => $event->previousName], 'new' => ['name' => $event->name]],
        );
    }
}
