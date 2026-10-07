<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Entity\Workspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class TaskAssigneeTest extends ApiTestCase
{
    private User $user;
    private User $colleague;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->colleague = UserFactory::createOne();
        $this->actingAs($this->user);
        $this->workspace = WorkspaceFactory::new()->withMembers([[$this->colleague, WorkspaceRole::MEMBER]])->create(['owner' => $this->user]);
    }

    private function task(?User $assignee = null, TaskStatus $status = TaskStatus::TODO, ?Workspace $workspace = null): Task
    {
        return TaskFactory::createOne([
            'workspace' => $workspace ?? $this->workspace,
            'assigneeId' => $assignee?->id(),
            'status' => $status,
            'cancellationReason' => TaskStatus::CANCELLED === $status ? 'Outdated' : null,
        ]);
    }

    private function assignee(Task $task): string
    {
        return $this->route('api_task_assign', ['id' => $task->getId()->toRfc4122()]);
    }

    private function assigneeOf(Task $task): ?string
    {
        $data = $this->jsonData($this->get($this->route('api_task_get', ['id' => $task->getId()->toRfc4122()])));

        return $data['relationships']['assignee']['data']['id'] ?? null;
    }

    private function member(User $user): string
    {
        return $this->route('api_workspace_member_remove', ['id' => $this->workspace->getId()->toRfc4122(), 'userId' => $user->id()]);
    }

    #[Test]
    public function newTaskShouldHaveNoAssignee(): void
    {
        $data = $this->jsonData($this->post($this->route('api_workspace_task_create', ['id' => $this->workspace->getId()->toRfc4122()]), ['title' => 'Buy milk']));

        self::assertNull($data['relationships']['assignee']['data']);
    }

    #[Test]
    public function assignShouldGiveTheTaskToAMemberAndRecordIt(): void
    {
        $task = $this->task();

        $data = $this->jsonData($this->put($this->assignee($task), ['user_id' => $this->colleague->id()]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['type' => 'users', 'id' => (string) $this->colleague->id()], $data['relationships']['assignee']['data']);

        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $task->getId()->toRfc4122());
        self::assertCount(1, $auditLogs);
        self::assertSame($this->user->id(), $auditLogs[0]->getActorId());
        self::assertEquals(['old' => ['assignee_id' => null], 'new' => ['assignee_id' => $this->colleague->id()]], $auditLogs[0]->getAttributeChanges());
    }

    #[Test]
    public function memberShouldBeAbleToTakeATask(): void
    {
        $task = $this->task();

        $this->put($this->assignee($task), ['user_id' => $this->user->id()]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame((string) $this->user->id(), $this->assigneeOf($task));
    }

    #[Test]
    public function taskShouldNotBeAssignedToAViewer(): void
    {
        $viewer = UserFactory::createOne();
        $workspace = WorkspaceFactory::new()->withMembers([[$viewer, WorkspaceRole::VIEWER]])->create(['owner' => $this->user]);
        $task = $this->task(workspace: $workspace);

        $this->put($this->assignee($task), ['user_id' => $viewer->id()]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function taskShouldNotBeAssignedToSomeoneOutsideTheWorkspace(): void
    {
        $this->put($this->assignee($this->task()), ['user_id' => UserFactory::createOne()->id()]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function taskShouldNotBeAssignedToAUserWhoDoesNotExist(): void
    {
        $this->put($this->assignee($this->task()), ['user_id' => 999999]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestWith([['user' => 7]])]
    #[TestWith([['user_id' => 0]])]
    #[TestWith([['user_id' => 'someone']])]
    public function assignWithAnInvalidBodyShouldReturn422(array $body): void
    {
        $this->put($this->assignee($this->task()), $body);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function unassignShouldLeaveTheTaskWithoutAnAssignee(): void
    {
        $task = $this->task($this->colleague);

        $data = $this->jsonData($this->delete($this->assignee($task)));

        self::assertResponseStatusCodeSame(200);
        self::assertNull($data['relationships']['assignee']['data']);
    }

    #[Test]
    #[TestWith([TaskStatus::COMPLETED])]
    #[TestWith([TaskStatus::CANCELLED])]
    public function finishedTaskShouldKeepItsAssignee(TaskStatus $status): void
    {
        $task = $this->task($this->colleague, $status);

        $this->put($this->assignee($task), ['user_id' => $this->user->id()]);
        self::assertResponseStatusCodeSame(409);

        $this->delete($this->assignee($task));
        self::assertResponseStatusCodeSame(409);

        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($task));
    }

    #[Test]
    public function viewerShouldNotAssignATask(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::VIEWER]])->create(['owner' => $this->colleague]);
        $task = $this->task(workspace: $workspace);

        $this->put($this->assignee($task), ['user_id' => $this->colleague->id()]);
        self::assertResponseStatusCodeSame(403);

        $this->delete($this->assignee($task));
        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function assignOfATaskInAWorkspaceOfStrangersShouldReturn404(): void
    {
        $this->put($this->assignee(TaskFactory::createOne()), ['user_id' => $this->user->id()]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function removedMemberShouldBeTakenOffUnfinishedTasksOnly(): void
    {
        $inProgress = $this->task($this->colleague, TaskStatus::IN_PROGRESS);
        $completed = $this->task($this->colleague, TaskStatus::COMPLETED);
        $ofTheOwner = $this->task($this->user);

        $this->delete($this->member($this->colleague));
        self::assertResponseStatusCodeSame(204);

        self::assertNull($this->assigneeOf($inProgress));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($completed));
        self::assertSame((string) $this->user->id(), $this->assigneeOf($ofTheOwner));

        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $inProgress->getId()->toRfc4122());
        self::assertSame($this->user->id(), $auditLogs[0]->getActorId());
        self::assertEquals(['old' => ['assignee_id' => $this->colleague->id()], 'new' => ['assignee_id' => null]], $auditLogs[0]->getAttributeChanges());
    }

    #[Test]
    public function memberWhoLeavesShouldBeTakenOffTheirTasks(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::MEMBER]])->create(['owner' => $this->colleague]);
        $task = $this->task($this->user, workspace: $workspace);

        $this->post($this->route('api_workspace_leave', ['id' => $workspace->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(204);

        $this->actingAs($this->colleague);
        self::assertNull($this->assigneeOf($task));
    }

    #[Test]
    #[TestWith(['viewer', false])]
    #[TestWith(['owner', true])]
    public function memberWhoBecomesAViewerShouldBeTakenOffTheirTasks(string $role, bool $keepsTheTask): void
    {
        $task = $this->task($this->colleague);

        $this->put($this->member($this->colleague), ['role' => $role]);
        self::assertResponseStatusCodeSame(200);

        self::assertSame($keepsTheTask ? (string) $this->colleague->id() : null, $this->assigneeOf($task));
    }

    #[Test]
    public function memberShouldStayWhenTheirTasksCannotBeTakenOffThem(): void
    {
        $task = $this->task($this->colleague);
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->delete($this->member($this->colleague));
        self::assertResponseStatusCodeSame(500);

        static::getContainer()->get(AuditLogFailureToggle::class)->disable();
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($task));
        $this->get($this->member($this->colleague));
        self::assertResponseStatusCodeSame(200);
    }
}
