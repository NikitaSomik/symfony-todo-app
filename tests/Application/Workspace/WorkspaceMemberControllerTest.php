<?php

declare(strict_types=1);

namespace App\Tests\Application\Workspace;

use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

final class WorkspaceMemberControllerTest extends ApiTestCase
{
    private User $user;
    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->colleague = UserFactory::createOne(['email' => 'colleague@example.com']);
        $this->actingAs($this->user);
    }

    private function ownedWorkspace(?WorkspaceRole $colleagueRole = null): Workspace
    {
        return WorkspaceFactory::new()
            ->withMembers(null === $colleagueRole ? [] : [$this->colleague->id() => $colleagueRole])
            ->create(['name' => 'Mobile team', 'owner' => $this->user]);
    }

    /** The current user is not the owner here: the colleague is, and the user has the given role. */
    private function workspaceOfTheColleague(WorkspaceRole $userRole): Workspace
    {
        return WorkspaceFactory::new()
            ->withMembers([$this->user->id() => $userRole])
            ->create(['name' => 'Mobile team', 'owner' => $this->colleague]);
    }

    private function members(Workspace $workspace): string
    {
        return $this->route('api_workspace_member_get_all', ['id' => $workspace->getId()->toRfc4122()]);
    }

    private function member(Workspace $workspace, User $user): string
    {
        return $this->route('api_workspace_member_remove', ['id' => $workspace->getId()->toRfc4122(), 'userId' => $user->id()]);
    }

    /** @return array<string, string> role by user id */
    private function roles(Response $response): array
    {
        $roles = [];

        foreach ($this->jsonData($response) as $member) {
            $roles[$member['relationships']['user']['data']['id']] = $member['attributes']['role'];
        }

        return $roles;
    }

    /** @return list<string> */
    private function auditMessages(Workspace $workspace): array
    {
        $records = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::WORKSPACE, $workspace->getId()->toRfc4122());

        return array_map(static fn ($record): string => $record->getMessage(), $records);
    }

    #[Test]
    public function getAllShouldListTheMembersToAnyMember(): void
    {
        $workspace = $this->workspaceOfTheColleague(WorkspaceRole::VIEWER);

        $response = $this->get($this->members($workspace));

        self::assertResponseIsSuccessful();
        self::assertSame(
            [(string) $this->colleague->id() => 'owner', (string) $this->user->id() => 'viewer'],
            $this->roles($response),
        );
    }

    #[Test]
    public function getAllOfAWorkspaceOfStrangersShouldReturn404(): void
    {
        $this->get($this->members(WorkspaceFactory::createOne()));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function addShouldMakeARegisteredUserAMember(): void
    {
        $workspace = $this->ownedWorkspace();

        $response = $this->post($this->members($workspace), ['email' => 'Colleague@Example.com']);
        $data = $this->jsonData($response);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('workspace_members', $data['type']);
        self::assertSame('member', $data['attributes']['role']);
        self::assertSame(['type' => 'users', 'id' => (string) $this->colleague->id()], $data['relationships']['user']['data']);
        self::assertSame(
            [sprintf('Added user %d to workspace "Mobile team" as member', $this->colleague->id())],
            $this->auditMessages($workspace),
        );
    }

    #[Test]
    public function addWhenNobodyIsRegisteredWithTheEmailShouldReturn422(): void
    {
        $response = $this->post($this->members($this->ownedWorkspace()), ['email' => 'nobody@example.com', 'role' => 'viewer']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('No user is registered with this email.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function addOfSomeoneWhoIsAlreadyAMemberShouldReturn409(): void
    {
        $response = $this->post($this->members($this->ownedWorkspace(WorkspaceRole::VIEWER)), ['email' => 'colleague@example.com']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('This user is already a member of the workspace.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    #[TestWith([['email' => 'not-an-email']])]
    #[TestWith([['email' => 'colleague@example.com', 'role' => 'admin']])]
    public function addWithAnInvalidBodyShouldReturn422(array $body): void
    {
        $this->post($this->members($this->ownedWorkspace()), $body);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestWith([WorkspaceRole::MEMBER])]
    #[TestWith([WorkspaceRole::VIEWER])]
    public function addByAMemberWhoIsNotAnOwnerShouldReturn403(WorkspaceRole $role): void
    {
        UserFactory::createOne(['email' => 'third@example.com']);

        $this->post($this->members($this->workspaceOfTheColleague($role)), ['email' => 'third@example.com']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function changeRoleShouldGiveTheMemberTheNewRole(): void
    {
        $workspace = $this->ownedWorkspace(WorkspaceRole::MEMBER);

        $response = $this->put($this->member($workspace, $this->colleague), ['role' => 'owner']);

        self::assertResponseIsSuccessful();
        self::assertSame('owner', $this->jsonAttributes($response)['role']);
        self::assertSame(
            [sprintf('Changed the role of user %d in workspace "Mobile team" from member to owner', $this->colleague->id())],
            $this->auditMessages($workspace),
        );
    }

    #[Test]
    public function lastOwnerShouldNotBeGivenAnotherRole(): void
    {
        $workspace = $this->ownedWorkspace(WorkspaceRole::MEMBER);

        $response = $this->put($this->member($workspace, $this->user), ['role' => 'member']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('A workspace must keep at least one owner.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function changeRoleOfSomeoneWhoIsNotAMemberShouldReturn404(): void
    {
        $this->put($this->member($this->ownedWorkspace(), $this->colleague), ['role' => 'viewer']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function changeRoleByAMemberWhoIsNotAnOwnerShouldReturn403(): void
    {
        $workspace = $this->workspaceOfTheColleague(WorkspaceRole::MEMBER);

        $this->put($this->member($workspace, $this->user), ['role' => 'owner']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function ownerShouldBeAbleToRemoveAMember(): void
    {
        $workspace = $this->ownedWorkspace(WorkspaceRole::MEMBER);

        $this->delete($this->member($workspace, $this->colleague));

        self::assertResponseStatusCodeSame(204);
        self::assertSame([(string) $this->user->id() => 'owner'], $this->roles($this->get($this->members($workspace))));
        self::assertSame(
            [sprintf('Removed user %d from workspace "Mobile team"', $this->colleague->id())],
            $this->auditMessages($workspace),
        );
    }

    #[Test]
    public function memberShouldBeAbleToLeave(): void
    {
        $workspace = $this->workspaceOfTheColleague(WorkspaceRole::VIEWER);

        $this->delete($this->member($workspace, $this->user));
        self::assertResponseStatusCodeSame(204);

        $this->get($this->members($workspace));
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function memberWhoIsNotAnOwnerShouldNotRemoveSomeoneElse(): void
    {
        $workspace = $this->workspaceOfTheColleague(WorkspaceRole::MEMBER);

        $this->delete($this->member($workspace, $this->colleague));

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function lastOwnerShouldNotLeave(): void
    {
        $workspace = $this->ownedWorkspace(WorkspaceRole::MEMBER);

        $this->delete($this->member($workspace, $this->user));

        self::assertResponseStatusCodeSame(409);
    }
}
