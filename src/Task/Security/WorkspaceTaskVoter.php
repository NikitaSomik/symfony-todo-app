<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, WorkspaceContext>
 */
final class WorkspaceTaskVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return TaskVoter::WRITE === $attribute && $subject instanceof WorkspaceContext;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $subject->userCan(WorkspacePermission::WORK_ON_TASKS);
    }
}
