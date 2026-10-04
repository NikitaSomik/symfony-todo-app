<?php

declare(strict_types=1);

namespace App\Workspace\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Workspace>
 */
final class WorkspaceVoter extends Voter
{
    public const string MANAGE = 'WORKSPACE_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof Workspace;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AuthenticatedUser && WorkspaceRole::OWNER === $subject->roleOf($user->id());
    }
}
