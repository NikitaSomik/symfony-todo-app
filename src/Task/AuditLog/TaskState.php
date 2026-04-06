<?php

declare(strict_types=1);

namespace App\Task\AuditLog;

use App\Task\Entity\Task;

final readonly class TaskState
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $status,
        public ?string $cancellationReason,
        public ?string $dueDate,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        return new self(
            title: $task->getTitle(),
            description: $task->getDescription(),
            status: $task->getStatus()->value,
            cancellationReason: $task->getCancellationReason(),
            dueDate: $task->getDueDate()?->format('Y-m-d'),
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
            Task::FIELD_STATUS => $this->status,
            Task::FIELD_CANCELLATION_REASON => $this->cancellationReason,
            Task::FIELD_DUE_DATE => $this->dueDate,
        ];
    }
}
