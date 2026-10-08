<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Security;

use App\Task\Entity\Task;
use App\Task\Security\TaskVoter;
use App\Workspace\Contract\WorkspaceAccess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What each role may do is covered end to end in TaskWorkspaceAccessTest; a request without
 * a user never reaches the voter there, because the firewall answers it first.
 */
final class TaskVoterTest extends TestCase
{
    #[Test]
    public function withoutAUserNothingShouldBeGranted(): void
    {
        $voter = new TaskVoter($this->createStub(WorkspaceAccess::class));

        $vote = $voter->vote(new NullToken(), new Task(id: Uuid::v7(), workspaceId: Uuid::v7(), creatorId: 1), [TaskVoter::WRITE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }
}
