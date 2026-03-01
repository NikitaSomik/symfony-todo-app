<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Entity\User;
use App\Task\Entity\Task;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const string ACCESS = 'TASK_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute && $subject instanceof Task;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return self::ACCESS === $attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Task::class, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $subject->getUser()->getId() === $user->getId();
    }
}
