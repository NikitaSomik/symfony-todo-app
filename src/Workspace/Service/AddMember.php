<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Auth\Contract\UserDirectory;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
use App\Workspace\Event\MemberAdded;
use App\Workspace\Exception\MemberAlreadyExistsException;
use App\Workspace\Exception\UserNotRegisteredException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AddMember
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private UserDirectory $users,
    ) {
    }

    public function handle(Workspace $workspace, string $email, WorkspaceRole $role, int $actorId): Membership
    {
        $userId = $this->users->findIdByEmail($email);

        if (null === $userId) {
            throw new UserNotRegisteredException();
        }

        try {
            return $this->em->wrapInTransaction(function () use ($workspace, $userId, $role, $actorId): Membership {
                $membership = $workspace->addMember($this->users->reference($userId), $role, $this->clock->now());

                $this->eventDispatcher->dispatch(new MemberAdded($workspace->getId()->toRfc4122(), $workspace->getName(), $userId, $role, $actorId));

                return $membership;
            });
        } catch (UniqueConstraintViolationException) {
            throw new MemberAlreadyExistsException();
        }
    }
}
