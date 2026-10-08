<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Task\Entity\Task;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task|WorkspaceContext>
 */
final class TaskVoter extends Voter
{
    public const string WRITE = 'TASK_WRITE';
    public const string DELETE = 'TASK_DELETE';

    private const array PERMISSIONS = [
        self::WRITE => WorkspacePermission::WORK_ON_TASKS,
        self::DELETE => WorkspacePermission::DELETE_TASKS,
    ];

    public function __construct(
        private readonly WorkspaceAccess $workspaces,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::PERMISSIONS[$attribute]) && ($subject instanceof Task || $subject instanceof WorkspaceContext);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof AuthenticatedUser) {
            return false;
        }

        $permission = self::PERMISSIONS[$attribute];

        return $subject instanceof Task
            ? $this->workspaces->can($subject->getWorkspaceId(), $user->id(), $permission)
            : $subject->userCan($permission);
    }
}
