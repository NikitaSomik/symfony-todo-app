<?php

declare(strict_types=1);

namespace App\Workspace\Entity;

use App\Auth\Contract\AuthenticatedUser;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Exception\LastOwnerException;
use App\Workspace\Exception\MemberAlreadyExistsException;
use App\Workspace\Exception\MemberNotFoundException;
use App\Workspace\Repository\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[ORM\Table(name: 'workspaces')]
class Workspace
{
    public const int NAME_MAX_LENGTH = 100;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'workspace', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['joinedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $members;

    public function __construct(Uuid $id, string $name, AuthenticatedUser $owner, \DateTimeImmutable $at)
    {
        $this->id = $id;
        $this->name = trim($name);
        $this->createdAt = $at;
        $this->members = new ArrayCollection([new Membership($this, $owner, WorkspaceRole::OWNER, $at)]);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = trim($name);
    }

    /** @return list<Membership> */
    public function getMembers(): array
    {
        return $this->members->getValues();
    }

    public function roleOf(int $userId): ?WorkspaceRole
    {
        return $this->membershipOf($userId)?->getRole();
    }

    public function addMember(AuthenticatedUser $user, WorkspaceRole $role, \DateTimeImmutable $at): Membership
    {
        if (null !== $this->membershipOf($user->id())) {
            throw new MemberAlreadyExistsException();
        }

        $membership = new Membership($this, $user, $role, $at);
        $this->members->add($membership);

        return $membership;
    }

    public function getMember(int $userId): Membership
    {
        return $this->membershipOf($userId) ?? throw new MemberNotFoundException();
    }

    public function changeRole(int $userId, WorkspaceRole $role): Membership
    {
        $membership = $this->getMember($userId);

        if (WorkspaceRole::OWNER !== $role && $this->isLastOwner($membership)) {
            throw new LastOwnerException();
        }

        $membership->changeRole($role);

        return $membership;
    }

    public function removeMember(int $userId): Membership
    {
        $membership = $this->getMember($userId);

        if ($this->isLastOwner($membership)) {
            throw new LastOwnerException();
        }

        $this->members->removeElement($membership);

        return $membership;
    }

    private function membershipOf(int $userId): ?Membership
    {
        foreach ($this->members as $membership) {
            if ($membership->getUserId() === $userId) {
                return $membership;
            }
        }

        return null;
    }

    private function isLastOwner(Membership $membership): bool
    {
        if (WorkspaceRole::OWNER !== $membership->getRole()) {
            return false;
        }

        foreach ($this->members as $other) {
            if ($other !== $membership && WorkspaceRole::OWNER === $other->getRole()) {
                return false;
            }
        }

        return true;
    }
}
