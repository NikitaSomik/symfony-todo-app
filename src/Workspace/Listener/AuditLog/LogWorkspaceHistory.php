<?php

declare(strict_types=1);

namespace App\Workspace\Listener\AuditLog;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Service\AuditLogLogger;
use App\Workspace\Event\MemberAdded;
use App\Workspace\Event\MemberRemoved;
use App\Workspace\Event\MemberRoleChanged;
use App\Workspace\Event\WorkspaceCreated;
use App\Workspace\Event\WorkspaceRenamed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class LogWorkspaceHistory
{
    public function __construct(
        private AuditLogLogger $auditLogLogger,
    ) {
    }

    #[AsEventListener]
    public function created(WorkspaceCreated $event): void
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

    #[AsEventListener]
    public function renamed(WorkspaceRenamed $event): void
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

    #[AsEventListener]
    public function memberAdded(MemberAdded $event): void
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

    #[AsEventListener]
    public function memberRoleChanged(MemberRoleChanged $event): void
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

    #[AsEventListener]
    public function memberRemoved(MemberRemoved $event): void
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
