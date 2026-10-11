<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\MemberRemoved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogMemberRemoved
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    public function __invoke(MemberRemoved $event): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::WORKSPACE,
            entityId: $event->workspaceId,
            actorId: $event->actorId,
            action: AuditLogAction::UPDATED,
            message: sprintf('Removed user %d from workspace "%s"', $event->userId, $event->workspaceName),
            attributeChanges: ['old' => ['member' => ['user_id' => $event->userId, 'role' => $event->role->value]], 'new' => ['member' => null]],
        );
    }
}
