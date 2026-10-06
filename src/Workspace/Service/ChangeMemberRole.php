<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
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
        return $this->em->wrapInTransaction(function () use ($workspace, $userId, $role, $actorId): Membership {
            $previousRole = $workspace->roleOf($userId) ?? throw new MemberNotFoundException();
            $membership = $workspace->changeRole($userId, $role);

            if ($previousRole !== $role) {
                $this->eventDispatcher->dispatch(new MemberRoleChanged($workspace->getId()->toRfc4122(), $workspace->getName(), $userId, $previousRole, $role, $actorId));
            }

            return $membership;
        });
    }
}
