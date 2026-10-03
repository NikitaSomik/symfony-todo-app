<?php

declare(strict_types=1);

namespace App\Task\Entity;

use App\Auth\Entity\User;
use App\Task\Enum\TaskStatus;
use App\Task\Exception\TaskTransitionNotAllowedException;
use App\Task\Repository\TaskRepository;
use App\Task\ValueObject\BlockReason;
use App\Task\ValueObject\CancellationReason;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    public const string FIELD_BLOCK_REASON = 'block_reason';
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

    #[ORM\Column(length: CancellationReason::MAX_LENGTH, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(length: BlockReason::MAX_LENGTH, nullable: true)]
    private ?string $blockReason = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Maintained by PostgreSQL, never written from PHP: it is mapped so that DQL search can use it
     * and schema comparison does not offer to drop the full-text search column.
     * The expression must stay in sync with the one created in Version20260326230945.
     *
     * Not marked as generated on purpose: Doctrine would read the column back after every write
     * without updating its snapshot, so the task would look changed and the next flush would send
     * an extra UPDATE (doctrine/orm#12017). The property therefore goes stale after a write, which is
     * fine: PHP never reads it, and DQL reads the column from the database.
     */
    #[ORM\Column(
        type: 'tsvector',
        nullable: true,
        insertable: false,
        updatable: false,
        columnDefinition: "tsvector GENERATED ALWAYS AS (setweight(to_tsvector('english', coalesce(title, '')), 'A') || setweight(to_tsvector('english', coalesce(description, '')), 'B')) STORED",
    )]
    private ?string $searchVector = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /**
     * Write-only on purpose: a transition adds its row here and nothing reads the collection, so
     * the history of a task is never loaded with it. Reading goes through the repository.
     *
     * @var Collection<int, TaskStatusChange>
     */
    #[ORM\OneToMany(targetEntity: TaskStatusChange::class, mappedBy: 'task', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
    private Collection $statusChanges;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
        $this->statusChanges = new ArrayCollection();
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

    public function start(\DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::IN_PROGRESS, $at);
    }

    public function submitForReview(\DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::IN_REVIEW, $at);
    }

    public function complete(\DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::COMPLETED, $at);
    }

    public function block(BlockReason $reason, \DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::BLOCKED, $at);
        $this->blockReason = $reason->value;
    }

    public function unblock(\DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::IN_PROGRESS, $at);
    }

    public function cancel(CancellationReason $reason, \DateTimeImmutable $at): void
    {
        $this->transitionTo(TaskStatus::CANCELLED, $at);
        $this->cancellationReason = $reason->value;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    private function transitionTo(TaskStatus $to, \DateTimeImmutable $at): void
    {
        if (!$this->status->canTransitionTo($to)) {
            throw new TaskTransitionNotAllowedException($this->status, $to);
        }

        $this->statusChanges->add(new TaskStatusChange($this, $this->status, $to, $at));
        $this->status = $to;
        // The reason describes the current block only; whatever leaves the status takes it away.
        $this->blockReason = null;
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
