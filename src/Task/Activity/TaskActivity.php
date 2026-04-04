<?php

declare(strict_types=1);

namespace App\Task\Activity;

use App\Auth\Entity\User;
use App\Shared\Activity\ChangeSetDetector;
use App\Shared\Activity\Enum\ActivityAction;
use App\Shared\Activity\Enum\ActivityEntityType;
use App\Shared\Activity\Formatter\ActivityMessageFormatter;
use App\Shared\Activity\Service\ActivityLogger;
use App\Task\Entity\Task;

final class TaskActivity
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
        private readonly ActivityLogger $activityLogger,
        private readonly ChangeSetDetector $changeSetDetector,
        private readonly ActivityMessageFormatter $messageFormatter,
    ) {
    }

    public function created(int $taskId, User $actor, TaskState $state): void
    {
        $this->activityLogger->log(
            entityType: ActivityEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: ActivityAction::CREATED,
            message: $this->messageFormatter->created(self::ENTITY_LABEL, $state->title),
            properties: ['attributes' => $state->toArray()],
        );
    }

    public function updated(int $taskId, User $actor, TaskState $previousState, TaskState $currentState): void
    {
        $changes = $this->changeSetDetector->detect($previousState->toArray(), $currentState->toArray());
        if (empty($changes)) {
            return;
        }

        $this->activityLogger->log(
            entityType: ActivityEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: ActivityAction::UPDATED,
            message: $this->messageFormatter->updated(
                entityLabel: self::ENTITY_LABEL,
                displayName: $currentState->title,
                changes: $changes,
                fieldLabels: self::FIELD_LABELS,
            ),
            attributeChanges: $this->updatedData($changes),
        );
    }

    public function deleted(int $taskId, User $actor, TaskState $state): void
    {
        $this->activityLogger->log(
            entityType: ActivityEntityType::TASK,
            entityId: $taskId,
            user: $actor,
            action: ActivityAction::DELETED,
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
