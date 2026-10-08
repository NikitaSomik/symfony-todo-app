<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Contract\MembershipEnded;
use App\Workspace\Entity\Workspace;
use App\Workspace\Event\MemberRemoved;
use App\Workspace\Exception\CannotRemoveYourselfException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RemoveMember
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Workspace $workspace, int $userId, int $actorId): void
    {
        if ($userId === $actorId) {
            throw new CannotRemoveYourselfException();
        }

        $this->em->wrapInTransaction(function () use ($workspace, $userId, $actorId): void {
            $membership = $workspace->removeMember($userId);

            $this->eventDispatcher->dispatch(new MemberRemoved(
                workspaceId: $workspace->getId()->toRfc4122(),
                workspaceName: $workspace->getName(),
                userId: $userId,
                role: $membership->getRole(),
                actorId: $actorId,
            ));
            $this->eventDispatcher->dispatch(new MembershipEnded($workspace->getId(), userId: $userId, actorId: $actorId));
        });
    }
}
