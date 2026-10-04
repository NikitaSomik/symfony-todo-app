<?php

declare(strict_types=1);

namespace App\AuditLog\Entity;

use App\AuditLog\Enum\AuditLogAction;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_logs_entity_created_at', columns: ['entity_type', 'entity_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_logs_actor_id', columns: ['actor_id'])]
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

    /**
     * A plain id, not a relation: the record states who acted and has to stay as it is when
     * that user is gone. Null for an action no user made.
     */
    #[ORM\Column(nullable: true)]
    private ?int $actorId;

    #[ORM\Column(length: 50, enumType: AuditLogAction::class)]
    private AuditLogAction $action;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $attributeChanges;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $metadata;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $attributeChanges
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        AuditLogEntityType $entityType,
        string $entityId,
        ?int $actorId,
        AuditLogAction $action,
        string $message,
        ?array $attributeChanges,
        ?array $metadata,
        \DateTimeImmutable $createdAt,
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->actorId = $actorId;
        $this->action = $action;
        $this->message = $message;
        $this->attributeChanges = $attributeChanges;
        $this->metadata = $metadata;
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

    public function getActorId(): ?int
    {
        return $this->actorId;
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
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
