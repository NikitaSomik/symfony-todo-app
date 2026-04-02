<?php

declare(strict_types=1);

namespace App\Task\Entity;

use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskStatusChangeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskStatusChangeRepository::class)]
#[ORM\Table(name: 'task_status_changes')]
#[ORM\Index(name: 'idx_task_status_changes_task_changed_at', columns: ['task_id', 'changed_at'])]
final class TaskStatusChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $fromStatus;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $toStatus;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function __construct(Task $task, TaskStatus $fromStatus, TaskStatus $toStatus, \DateTimeImmutable $changedAt)
    {
        $this->task = $task;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->changedAt = $changedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getFromStatus(): TaskStatus
    {
        return $this->fromStatus;
    }

    public function getToStatus(): TaskStatus
    {
        return $this->toStatus;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
