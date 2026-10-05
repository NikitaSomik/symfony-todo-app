<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Auth\Contract\AuthenticatedUser;
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
        return $this->em->wrapInTransaction(function () use ($name, $ownerId): Workspace {
            $owner = $this->em->getReference(AuthenticatedUser::class, $ownerId);
            \assert(null !== $owner);

            $workspace = new Workspace(Uuid::v7(), $name, $owner, $this->clock->now());
            $this->em->persist($workspace);

            $this->eventDispatcher->dispatch(new WorkspaceCreated($workspace->getId()->toRfc4122(), $workspace->getName(), $ownerId));

            return $workspace;
        });
    }
}
