<?php

declare(strict_types=1);

namespace App\Billing\Security;

use App\Workspace\Contract\WorkspacePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, WorkspaceContext>
 */
final class BillingVoter extends Voter
{
    public const string MANAGE = 'BILLING_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof WorkspaceContext;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $subject->userCan(WorkspacePermission::MANAGE_BILLING);
    }
}
