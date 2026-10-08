<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Contract\MemberPermissionsChanged;
use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use App\Workspace\Event\MemberRoleChanged;
use App\Workspace\Exception\MemberNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ChangeMemberRole
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Workspace $workspace, int $userId, WorkspaceRole $role, int $actorId): Membership
    {
        $previousRole = $workspace->roleOf($userId);

        if (null === $previousRole) {
            throw new MemberNotFoundException();
        }

        return $this->em->wrapInTransaction(function () use ($workspace, $userId, $role, $previousRole, $actorId): Membership {
            $membership = $workspace->changeRole($userId, $role);

            if ($previousRole !== $role) {
                $this->eventDispatcher->dispatch(new MemberRoleChanged(
                    workspaceId: $workspace->getId()->toRfc4122(),
                    workspaceName: $workspace->getName(),
                    userId: $userId,
                    previousRole: $previousRole,
                    role: $role,
                    actorId: $actorId,
                ));
                $this->eventDispatcher->dispatch(new MemberPermissionsChanged(
                    workspaceId: $workspace->getId(),
                    userId: $userId,
                    permissions: $role->permissions(),
                    actorId: $actorId,
                ));
            }

            return $membership;
        });
    }
}
