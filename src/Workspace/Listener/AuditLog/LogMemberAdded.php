<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\MemberAdded;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogMemberAdded
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(MemberAdded $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->actorId,
            action: AuditLogAction::UPDATED,
            message: sprintf('Added user %d to workspace "%s" as %s', $event->userId, $event->workspaceName, $event->role->value),
            attributeChanges: ['old' => ['member' => null], 'new' => ['member' => ['user_id' => $event->userId, 'role' => $event->role->value]]],
        );
    }
}
