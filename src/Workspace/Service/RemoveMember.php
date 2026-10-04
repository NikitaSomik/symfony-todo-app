<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Entity\Workspace;
use App\Workspace\Event\MemberRemoved;
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
        $this->em->wrapInTransaction(function () use ($workspace, $userId, $actorId): void {
            $membership = $workspace->removeMember($userId);

            $this->eventDispatcher->dispatch(new MemberRemoved($workspace->getId()->toRfc4122(), $workspace->getName(), $userId, $membership->getRole(), $actorId));
        });
    }
}
