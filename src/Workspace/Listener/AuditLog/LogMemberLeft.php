<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\MemberLeft;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogMemberLeft
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(MemberLeft $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->userId,
            action: AuditLogAction::UPDATED,
            message: sprintf('User %d left workspace "%s"', $event->userId, $event->workspaceName),
            attributeChanges: ['old' => ['member' => ['user_id' => $event->userId, 'role' => $event->role->value]], 'new' => ['member' => null]],
        );
    }
}
