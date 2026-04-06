<?php

declare(strict_types=1);

namespace App\Shared\AuditLog\Entity;

use App\Auth\Entity\User;
use App\Shared\AuditLog\Enum\AuditLogAction;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use App\Shared\AuditLog\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_logs_entity_created_at', columns: ['entity_type', 'entity_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_logs_user_id', columns: ['user_id'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, enumType: AuditLogEntityType::class)]
    private AuditLogEntityType $entityType;

    #[ORM\Column(type: Types::GUID)]
    private string $entityId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 50, enumType: AuditLogAction::class)]
    private AuditLogAction $action;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $attributeChanges;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $properties;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $attributeChanges
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        AuditLogEntityType $entityType,
        string $entityId,
        ?User $user,
        AuditLogAction $action,
        string $message,
        ?array $attributeChanges,
        ?array $properties,
        \DateTimeImmutable $createdAt,
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->user = $user;
        $this->action = $action;
        $this->message = $message;
        $this->attributeChanges = $attributeChanges;
        $this->properties = $properties;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): AuditLogEntityType
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAction(): AuditLogAction
    {
        return $this->action;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAttributeChanges(): ?array
    {
        return $this->attributeChanges;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
