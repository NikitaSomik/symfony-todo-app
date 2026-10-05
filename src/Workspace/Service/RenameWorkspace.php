<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Workspace\Entity\Workspace;
use App\Workspace\Event\WorkspaceRenamed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RenameWorkspace
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Workspace $workspace, string $name, int $actorId): Workspace
    {
        return $this->em->wrapInTransaction(function () use ($workspace, $name, $actorId): Workspace {
            $previousName = $workspace->getName();
            $workspace->rename($name);

            if ($previousName !== $workspace->getName()) {
                $this->eventDispatcher->dispatch(new WorkspaceRenamed($workspace->getId()->toRfc4122(), $previousName, $workspace->getName(), $actorId));
            }

            return $workspace;
        });
    }
}
