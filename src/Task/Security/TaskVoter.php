<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Task\Entity\Task;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspaceRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task|WorkspaceContext>
 */
final class TaskVoter extends Voter
{
    public const string WRITE = 'TASK_WRITE';

    public function __construct(
        private readonly WorkspaceAccess $workspaces,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::WRITE === $attribute && ($subject instanceof Task || $subject instanceof WorkspaceContext);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof AuthenticatedUser) {
            return false;
        }

        $role = $subject instanceof Task
            ? $this->workspaces->roleOf($subject->getWorkspaceId(), $user->id())
            : $subject->role;

        return null !== $role && WorkspaceRole::VIEWER !== $role;
    }
}
