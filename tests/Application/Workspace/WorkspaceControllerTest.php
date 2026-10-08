<?php

declare(strict_types=1);

namespace App\Tests\Application\Workspace;

use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use App\Auth\Contract\UserRegistered;
use App\Auth\Entity\User;
use App\Auth\Repository\UserRepository;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class WorkspaceControllerTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    /** @return list<string> */
    private function names(Response $response): array
    {
        return array_map(static fn (array $workspace): string => $workspace['attributes']['name'], $this->jsonData($response));
    }

    #[Test]
    public function registrationShouldGiveTheUserAPersonalWorkspace(): void
    {
        $this->post($this->route('api_auth_register'), ['email' => 'new@example.com', 'password' => 'secret123']);
        self::assertResponseStatusCodeSame(201);

        $registered = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'new@example.com']);
        $this->actingAs($registered);

        $data = $this->jsonData($this->get($this->route('api_workspace_get_all')));

        self::assertCount(1, $data);
        self::assertSame('Personal', $data[0]['attributes']['name']);
        self::assertSame('owner', $data[0]['attributes']['role']);
    }

    #[Test]
    public function registrationShouldBeUndoneWhenWhatItSetsOffFails(): void
    {
        static::getContainer()->get('event_dispatcher')->addListener(
            UserRegistered::class,
            static fn () => throw new \RuntimeException('The personal workspace could not be created.'),
        );

        $this->post($this->route('api_auth_register'), ['email' => 'new@example.com', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(500);
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'new@example.com']));
    }

    #[Test]
    public function createShouldMakeTheUserItsOwner(): void
    {
        $response = $this->post($this->route('api_workspace_create'), ['name' => '  Mobile team  ']);
        $data = $this->jsonData($response);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('workspaces', $data['type']);
        self::assertSame('Mobile team', $data['attributes']['name']);
        self::assertSame('owner', $data['attributes']['role']);
        self::assertSame($this->route('api_workspace_get', ['id' => $data['id']]), $response->headers->get('Location'));
        self::assertSame($this->route('api_workspace_member_get_all', ['id' => $data['id']]), $data['links']['members']);
    }

    #[Test]
    public function nameShouldBeMeasuredWithoutTheSpacesAroundIt(): void
    {
        $name = str_repeat('a', Workspace::NAME_MAX_LENGTH);

        $data = $this->jsonData($this->post($this->route('api_workspace_create'), ['name' => '  '.$name.'  ']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame($name, $data['attributes']['name']);
    }

    #[Test]
    public function createShouldLeaveARecordInTheAuditLog(): void
    {
        $id = $this->jsonData($this->post($this->route('api_workspace_create'), ['name' => 'Mobile team']))['id'];

        $records = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::WORKSPACE, $id);

        self::assertCount(1, $records);
        self::assertSame('Created workspace "Mobile team"', $records[0]->getMessage());
        self::assertSame($this->user->getId(), $records[0]->getActorId());
    }

    #[Test]
    public function createWhenNameIsBlankShouldReturn422(): void
    {
        $this->post($this->route('api_workspace_create'), ['name' => '   ']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function getAllShouldReturnOnlyTheWorkspacesTheUserBelongsTo(): void
    {
        WorkspaceFactory::createOne(['name' => 'Mine', 'owner' => $this->user]);
        WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::VIEWER]])->create(['name' => 'Shared with me']);
        WorkspaceFactory::createOne(['name' => 'Somebody else\'s']);

        $response = $this->get($this->route('api_workspace_get_all'));

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(['Mine', 'Shared with me'], $this->names($response));
    }

    #[Test]
    public function getShouldTellTheUserTheirRole(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::VIEWER]])->create();

        $response = $this->get($this->route('api_workspace_get', ['id' => $workspace->getId()->toRfc4122()]));

        self::assertResponseIsSuccessful();
        self::assertSame('viewer', $this->jsonAttributes($response)['role']);
    }

    #[Test]
    public function getWhenTheUserIsNotAMemberShouldAnswerLikeAMissingWorkspace(): void
    {
        $foreign = WorkspaceFactory::createOne();

        $foreignResponse = $this->get($this->route('api_workspace_get', ['id' => $foreign->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(404);

        $missingResponse = $this->get($this->route('api_workspace_get', ['id' => Uuid::v7()->toRfc4122()]));
        self::assertResponseStatusCodeSame(404);

        self::assertSame($this->json($missingResponse)['errors'][0]['status'], $this->json($foreignResponse)['errors'][0]['status']);
    }

    #[Test]
    public function renameShouldBeAllowedToAnOwner(): void
    {
        $workspace = WorkspaceFactory::createOne(['name' => 'Mobile team', 'owner' => $this->user]);
        $id = $workspace->getId()->toRfc4122();

        $response = $this->put($this->route('api_workspace_rename', ['id' => $id]), ['name' => 'Apps team']);

        self::assertResponseIsSuccessful();
        self::assertSame('Apps team', $this->jsonAttributes($response)['name']);

        $records = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::WORKSPACE, $id);
        self::assertSame('Renamed workspace "Mobile team" to "Apps team"', $records[0]->getMessage());
    }

    #[Test]
    public function renameByAMemberWhoIsNotAnOwnerShouldReturn403(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::MEMBER]])->create();

        $response = $this->put($this->route('api_workspace_rename', ['id' => $workspace->getId()->toRfc4122()]), ['name' => 'Apps team']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('403', $this->json($response)['errors'][0]['status']);
    }

    #[Test]
    public function permissionShouldBeCheckedBeforeTheBody(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::MEMBER]])->create();

        $this->put($this->route('api_workspace_rename', ['id' => $workspace->getId()->toRfc4122()]), ['name' => '']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function renameOfAWorkspaceOfStrangersShouldReturn404EvenWithAnInvalidBody(): void
    {
        $foreign = WorkspaceFactory::createOne();

        $this->put($this->route('api_workspace_rename', ['id' => $foreign->getId()->toRfc4122()]), ['name' => '']);

        self::assertResponseStatusCodeSame(404);
    }
}
