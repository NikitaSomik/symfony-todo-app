<?php

declare(strict_types=1);

namespace App\Workspace\Entity;

use App\Workspace\Enum\WorkspaceRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_members')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_members_user_workspace', columns: ['user_id', 'workspace_id'])]
#[ORM\Index(name: 'idx_workspace_members_workspace_id', columns: ['workspace_id'])]
final class Membership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Workspace $workspace,

        #[ORM\Column]
        private int $userId,

        #[ORM\Column(length: 20, enumType: WorkspaceRole::class)]
        private WorkspaceRole $role,

        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $joinedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRole(): WorkspaceRole
    {
        return $this->role;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /** Only the workspace changes a role: it is the one that knows whether an owner remains. */
    public function changeRole(WorkspaceRole $role): void
    {
        $this->role = $role;
    }
}
