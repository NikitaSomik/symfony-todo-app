<?php

declare(strict_types=1);

namespace App\Task\AuditLog;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;

final readonly class TaskState
{
    public function __construct(
        public string $title,
        public ?string $description,
        public TaskStatus $status,
        public ?string $cancellationReason,
        public ?string $blockReason,
        public ?string $dueDate,
        public ?int $assigneeId,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        return new self(
            title: $task->getTitle(),
            description: $task->getDescription(),
            status: $task->getStatus(),
            cancellationReason: $task->getCancellationReason(),
            blockReason: $task->getBlockReason(),
            dueDate: $task->getDueDate()?->format('Y-m-d'),
            assigneeId: $task->getAssigneeId(),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            Task::FIELD_TITLE => $this->title,
            Task::FIELD_DESCRIPTION => $this->description,
            Task::FIELD_STATUS => $this->status->value,
            Task::FIELD_CANCELLATION_REASON => $this->cancellationReason,
            Task::FIELD_BLOCK_REASON => $this->blockReason,
            Task::FIELD_DUE_DATE => $this->dueDate,
            Task::FIELD_ASSIGNEE_ID => $this->assigneeId,
        ];
    }
}
