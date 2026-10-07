<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class TaskWorkspaceAccessTest extends ApiTestCase
{
    private User $user;
    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->colleague = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    /** A workspace of the colleague in which the current user has the given role. */
    private function workspaceWhereUserIs(WorkspaceRole $role): Workspace
    {
        return WorkspaceFactory::new()->withMembers([[$this->user, $role]])->create(['owner' => $this->colleague]);
    }

    private function tasksOf(Workspace $workspace): string
    {
        return $this->route('api_workspace_task_get_all', ['id' => $workspace->getId()->toRfc4122()]);
    }

    /** @return list<string> */
    private function titles(string $uri): array
    {
        $titles = array_column(array_column($this->jsonData($this->get($uri)), 'attributes'), 'title');
        sort($titles);

        return $titles;
    }

    #[Test]
    public function createShouldPutTheTaskIntoTheWorkspaceAndNameItsCreator(): void
    {
        $workspace = $this->workspaceWhereUserIs(WorkspaceRole::MEMBER);

        $data = $this->jsonData($this->post($this->tasksOf($workspace), ['title' => 'Buy milk']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['type' => 'workspaces', 'id' => $workspace->getId()->toRfc4122()], $data['relationships']['workspace']['data']);
        self::assertSame(['type' => 'users', 'id' => (string) $this->user->id()], $data['relationships']['creator']['data']);
    }

    #[Test]
    #[TestWith([['title' => 'Buy milk']])]
    #[TestWith([['title' => '']])]
    public function createInAWorkspaceOfStrangersShouldReturn404WhateverTheBody(array $body): void
    {
        $this->post($this->tasksOf(WorkspaceFactory::createOne()), $body);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestWith([['title' => 'Buy milk']])]
    #[TestWith([['title' => '']])]
    public function createByAViewerShouldReturn403WhateverTheBody(array $body): void
    {
        $this->post($this->tasksOf($this->workspaceWhereUserIs(WorkspaceRole::VIEWER)), $body);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function listOfAWorkspaceShouldHoldOnlyItsTasks(): void
    {
        $workspace = $this->workspaceWhereUserIs(WorkspaceRole::VIEWER);
        TaskFactory::createOne(['workspace' => $workspace, 'title' => 'Team task']);
        TaskFactory::createOne(['workspace' => WorkspaceFactory::createOne(['owner' => $this->user]), 'title' => 'Personal task']);

        self::assertSame(['Team task'], $this->titles($this->tasksOf($workspace)));
    }

    #[Test]
    public function listOfAWorkspaceOfStrangersShouldReturn404(): void
    {
        $this->get($this->tasksOf(WorkspaceFactory::createOne()));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function memberShouldWorkOnATaskSomeoneElseCreated(): void
    {
        $task = TaskFactory::createOne(['workspace' => $this->workspaceWhereUserIs(WorkspaceRole::MEMBER)]);
        $id = ['id' => $task->getId()->toRfc4122()];

        $this->get($this->route('api_task_get', $id));
        self::assertResponseStatusCodeSame(200);

        $this->post($this->route('api_task_start', $id));
        self::assertResponseStatusCodeSame(200);

        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $id['id']);
        self::assertSame($this->user->id(), $auditLogs[0]->getActorId());
    }

    #[Test]
    public function viewerShouldReadATaskAndItsHistory(): void
    {
        $task = TaskFactory::createOne(['workspace' => $this->workspaceWhereUserIs(WorkspaceRole::VIEWER)]);
        $id = ['id' => $task->getId()->toRfc4122()];

        $this->get($this->route('api_task_get', $id));
        self::assertResponseStatusCodeSame(200);

        $this->get($this->route('api_task_get_audit_logs', $id));
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    #[TestWith(['PUT', 'api_task_update', ['title' => 'Buy bread']])]
    #[TestWith(['PUT', 'api_task_update', ['title' => '']])]
    #[TestWith(['DELETE', 'api_task_delete', []])]
    #[TestWith(['POST', 'api_task_start', []])]
    #[TestWith(['POST', 'api_task_cancel', ['reason' => 'No longer needed']])]
    #[TestWith(['POST', 'api_task_cancel', []])]
    public function viewerShouldNotChangeATask(string $method, string $route, array $body): void
    {
        $workspace = $this->workspaceWhereUserIs(WorkspaceRole::VIEWER);
        $task = TaskFactory::createOne(['workspace' => $workspace, 'title' => 'Buy milk']);
        $uri = $this->route($route, ['id' => $task->getId()->toRfc4122()]);

        match ($method) {
            'PUT' => $this->put($uri, $body),
            'POST' => $this->post($uri, $body),
            'DELETE' => $this->delete($uri),
        };

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['Buy milk'], $this->titles($this->tasksOf($workspace)));
    }

    #[Test]
    public function taskShouldDisappearForSomeoneWhoLeftItsWorkspace(): void
    {
        $workspace = $this->workspaceWhereUserIs(WorkspaceRole::MEMBER);
        $task = TaskFactory::createOne(['workspace' => $workspace]);

        $this->post($this->route('api_workspace_leave', ['id' => $workspace->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(204);

        $this->get($this->route('api_task_get', ['id' => $task->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(404);
    }
}
