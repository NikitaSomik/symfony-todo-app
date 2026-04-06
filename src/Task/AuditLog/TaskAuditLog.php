<?php

declare(strict_types=1);

namespace App\Task\AuditLog;

use App\Auth\Entity\User;
use App\Shared\AuditLog\ChangeSetDetector;
use App\Shared\AuditLog\Enum\AuditLogAction;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use App\Shared\AuditLog\Formatter\AuditLogMessageFormatter;
use App\Shared\AuditLog\Service\AuditLogLogger;
use App\Task\Entity\Task;

final class TaskAuditLog
{
    private const string ENTITY_LABEL = 'task';
    private const array FIELD_LABELS = [
        Task::FIELD_TITLE => 'title',
        Task::FIELD_DESCRIPTION => 'description',
        Task::FIELD_STATUS => 'status',
        Task::FIELD_CANCELLATION_REASON => 'cancellation reason',
        Task::FIELD_DUE_DATE => 'due date',
    ];

    public function __construct(
        private readonly AuditLogLogger $auditLogLogger,
        private readonly ChangeSetDetector $changeSetDetector,
        private readonly AuditLogMessageFormatter $messageFormatter,
    ) {
    }

    public function created(string $taskId, User $actor, TaskState $state): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: AuditLogAction::CREATED,
            message: $this->messageFormatter->created(self::ENTITY_LABEL, $state->title),
            properties: ['attributes' => $state->toArray()],
        );
    }

    public function updated(string $taskId, User $actor, TaskState $previousState, TaskState $currentState): void
    {
        $changes = $this->changeSetDetector->detect($previousState->toArray(), $currentState->toArray());
        if (empty($changes)) {
            return;
        }

        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: AuditLogAction::UPDATED,
            message: $this->messageFormatter->updated(
                entityLabel: self::ENTITY_LABEL,
                displayName: $currentState->title,
                changes: $changes,
                fieldLabels: self::FIELD_LABELS,
            ),
            attributeChanges: $this->updatedData($changes),
        );
    }

    public function deleted(string $taskId, User $actor, TaskState $state): void
    {
        $this->auditLogLogger->log(
            entityType: AuditLogEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: AuditLogAction::DELETED,
            message: $this->messageFormatter->deleted(self::ENTITY_LABEL, $state->title),
            properties: ['attributes' => $state->toArray()],
        );
    }

    /**
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes
     *
     * @return array{
     *     old: array<string, scalar|null>,
     *     attributes: array<string, scalar|null>
     * }
     */
    private function updatedData(array $changes): array
    {
        $old = [];
        $attributes = [];

        foreach ($changes as $field => $change) {
            $old[$field] = $change['old'];
            $attributes[$field] = $change['new'];
        }

        return [
            'old' => $old,
            'attributes' => $attributes,
        ];
    }
}
