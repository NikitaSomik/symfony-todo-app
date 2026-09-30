<?php

declare(strict_types=1);

namespace App\Task\Entity;

use App\Auth\Entity\User;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(name: 'idx_tasks_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_tasks_search_vector', columns: ['search_vector'])]
#[ORM\HasLifecycleCallbacks]
class Task
{
    public const string FIELD_ID = 'id';
    public const string FIELD_TITLE = 'title';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_STATUS = 'status';
    public const string FIELD_CANCELLATION_REASON = 'cancellation_reason';
    public const string FIELD_DUE_DATE = 'due_date';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';

    /** Dictionary of the generated search_vector column below; queries must use the same one. */
    public const string SEARCH_CONFIG = 'english';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::TODO;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Maintained by PostgreSQL, never written from PHP: it is mapped only so that schema
     * comparison knows about it and does not offer to drop the full-text search column.
     * The expression must stay in sync with the one created in Version20260326230945.
     */
    #[ORM\Column(
        type: 'tsvector',
        nullable: true,
        insertable: false,
        updatable: false,
        generated: 'ALWAYS',
        columnDefinition: "tsvector GENERATED ALWAYS AS (setweight(to_tsvector('english', coalesce(title, '')), 'A') || setweight(to_tsvector('english', coalesce(description, '')), 'B')) STORED",
    )]
    private ?string $searchVector = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function changeStatus(TaskStatus $status): void
    {
        if (TaskStatus::CANCELLED !== $status) {
            $this->cancellationReason = null;
        }

        $this->status = $status;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(string $reason): void
    {
        if (TaskStatus::CANCELLED !== $this->status) {
            throw new \LogicException('Cancellation reason can only be set when task is cancelled.');
        }

        $this->cancellationReason = $reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
