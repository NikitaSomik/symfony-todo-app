<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workspace\Entity;

use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use App\Workspace\Exception\LastOwnerException;
use App\Workspace\Exception\MemberAlreadyExistsException;
use App\Workspace\Exception\MemberNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkspaceTest extends TestCase
{
    private const int OWNER = 1;
    private const int COLLEAGUE = 2;

    private function workspace(): Workspace
    {
        return new Workspace(Uuid::v7(), 'Mobile team', self::OWNER, new \DateTimeImmutable('2026-04-01 10:00:00'));
    }

    #[Test]
    public function newWorkspaceShouldHaveItsCreatorAsTheOnlyOwner(): void
    {
        $workspace = $this->workspace();

        self::assertSame(WorkspaceRole::OWNER, $workspace->roleOf(self::OWNER));
        self::assertNull($workspace->roleOf(self::COLLEAGUE));
        self::assertCount(1, $workspace->getMembers());
    }

    #[Test]
    public function userShouldNotBeAddedTwice(): void
    {
        $workspace = $this->workspace();
        $workspace->addMember(self::COLLEAGUE, WorkspaceRole::MEMBER, new \DateTimeImmutable());

        $this->expectException(MemberAlreadyExistsException::class);

        $workspace->addMember(self::COLLEAGUE, WorkspaceRole::VIEWER, new \DateTimeImmutable());
    }

    #[Test]
    public function lastOwnerShouldNotBeRemoved(): void
    {
        $workspace = $this->workspace();
        $workspace->addMember(self::COLLEAGUE, WorkspaceRole::MEMBER, new \DateTimeImmutable());

        $this->expectException(LastOwnerException::class);

        $workspace->removeMember(self::OWNER);
    }

    #[Test]
    public function lastOwnerShouldNotBeGivenAnotherRole(): void
    {
        $workspace = $this->workspace();

        $this->expectException(LastOwnerException::class);

        $workspace->changeRole(self::OWNER, WorkspaceRole::MEMBER);
    }

    #[Test]
    public function ownerShouldBeAbleToStepDownOnceAnotherOwnerExists(): void
    {
        $workspace = $this->workspace();
        $workspace->addMember(self::COLLEAGUE, WorkspaceRole::OWNER, new \DateTimeImmutable());

        $workspace->changeRole(self::OWNER, WorkspaceRole::MEMBER);
        $workspace->removeMember(self::OWNER);

        self::assertNull($workspace->roleOf(self::OWNER));
        self::assertSame(WorkspaceRole::OWNER, $workspace->roleOf(self::COLLEAGUE));
    }

    #[Test]
    public function roleOfSomeoneWhoIsNotAMemberShouldNotBeChanged(): void
    {
        $this->expectException(MemberNotFoundException::class);

        $this->workspace()->changeRole(self::COLLEAGUE, WorkspaceRole::OWNER);
    }
}
