<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\MemberRoleChanged;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogMemberRoleChanged
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(MemberRoleChanged $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->actorId,
            action: AuditLogAction::UPDATED,
            message: sprintf('Changed the role of user %d in workspace "%s" from %s to %s', $event->userId, $event->workspaceName, $event->previousRole->value, $event->role->value),
            attributeChanges: [
                'old' => ['member' => ['user_id' => $event->userId, 'role' => $event->previousRole->value]],
                'new' => ['member' => ['user_id' => $event->userId, 'role' => $event->role->value]],
            ],
        );
    }
}
