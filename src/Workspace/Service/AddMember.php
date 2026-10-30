<?php

declare(strict_types=1);

namespace App\Workspace\Service;

use App\Auth\Contract\UserDirectory;
use App\Workspace\Contract\PlanLimits;
use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use App\Workspace\Event\MemberAdded;
use App\Workspace\Exception\MemberAlreadyExistsException;
use App\Workspace\Exception\PlanLimitExceededException;
use App\Workspace\Exception\UserNotRegisteredException;
use App\Workspace\Repository\WorkspaceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
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
        private WorkspaceRepository $workspaces,
        private PlanLimits $planLimits,
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
                // Two additions racing for the last place take turns here, and the second counts the first.
                $this->em->lock($workspace, LockMode::PESSIMISTIC_WRITE);
                $membership = $workspace->addMember($userId, $role, $this->clock->now());

                if (!$this->planLimits->allowsAnotherMember($workspace->getId(), $this->workspaces->countMembers($workspace->getId()))) {
                    throw PlanLimitExceededException::forMembers();
                }

                $this->eventDispatcher->dispatch(new MemberAdded(
                    workspaceId: $workspace->getId()->toRfc4122(),
                    workspaceName: $workspace->getName(),
                    userId: $userId,
                    role: $role,
                    actorId: $actorId,
                ));

                return $membership;
            });
        } catch (UniqueConstraintViolationException) {
            throw new MemberAlreadyExistsException();
        }
    }
}
