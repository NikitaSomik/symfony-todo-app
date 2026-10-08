<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Contract\MembershipEnded;
use App\Workspace\Entity\Workspace;
use App\Workspace\Event\MemberLeft;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class LeaveWorkspace
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Workspace $workspace, int $userId): void
    {
        $this->em->wrapInTransaction(function () use ($workspace, $userId): void {
            $membership = $workspace->removeMember($userId);

            $this->eventDispatcher->dispatch(new MemberLeft(
                workspaceId: $workspace->getId()->toRfc4122(),
                workspaceName: $workspace->getName(),
                userId: $userId,
                role: $membership->getRole(),
            ));
            $this->eventDispatcher->dispatch(new MembershipEnded($workspace->getId(), userId: $userId, actorId: $userId));
        });
    }
}
