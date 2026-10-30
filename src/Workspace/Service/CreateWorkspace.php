<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Entity\Workspace;
use App\Workspace\Event\WorkspaceCreated;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CreateWorkspace
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(string $name, int $ownerId): Workspace
    {
        return $this->create(new Workspace(Uuid::v7(), $name, $ownerId, $this->clock->now()), $ownerId);
    }

    public function personal(string $name, int $ownerId): Workspace
    {
        return $this->create(Workspace::personal(Uuid::v7(), $name, $ownerId, $this->clock->now()), $ownerId);
    }

    private function create(Workspace $workspace, int $ownerId): Workspace
    {
        return $this->em->wrapInTransaction(function () use ($workspace, $ownerId): Workspace {
            $this->em->persist($workspace);

            $this->eventDispatcher->dispatch(new WorkspaceCreated(
                workspaceId: $workspace->getId()->toRfc4122(),
                name: $workspace->getName(),
                actorId: $ownerId,
            ));

            return $workspace;
        });
    }
}
