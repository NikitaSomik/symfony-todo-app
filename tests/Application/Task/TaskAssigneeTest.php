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
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
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
            'blockReason' => TaskStatus::BLOCKED === $status ? 'Waiting for access' : null,
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

    private function reassign(?Workspace $workspace = null): string
    {
        return $this->route('api_workspace_task_reassign', ['id' => ($workspace ?? $this->workspace)->getId()->toRfc4122()]);
    }

    /** @return list<array{old: array<string, mixed>, new: array<string, mixed>}> */
    private function assigneeChanges(Task $task): array
    {
        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $task->getId()->toRfc4122());

        return array_map(static function ($auditLog): array {
            $changes = $auditLog->getAttributeChanges();

            return ['old' => $changes['old'], 'new' => $changes['new']];
        }, $auditLogs);
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
    public function assignShouldMoveATaskFromOneMemberToAnother(): void
    {
        $task = $this->task($this->colleague);

        $this->put($this->assignee($task), ['user_id' => $this->user->id()]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame((string) $this->user->id(), $this->assigneeOf($task));
        self::assertEquals(
            [['old' => ['assignee_id' => $this->colleague->id()], 'new' => ['assignee_id' => $this->user->id()]]],
            $this->assigneeChanges($task),
        );
    }

    #[Test]
    #[TestWith([TaskStatus::IN_PROGRESS])]
    #[TestWith([TaskStatus::IN_REVIEW])]
    #[TestWith([TaskStatus::BLOCKED])]
    public function taskThatIsNotFinishedShouldBeAssignableInAnyStatus(TaskStatus $status): void
    {
        $task = $this->task(status: $status);

        $this->put($this->assignee($task), ['user_id' => $this->colleague->id()]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($task));
    }

    #[Test]
    public function assignToTheSameMemberAgainShouldLeaveNoRecord(): void
    {
        $task = $this->task($this->colleague);

        $this->put($this->assignee($task), ['user_id' => $this->colleague->id()]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->assigneeChanges($task));
    }

    #[Test]
    public function unassignOfATaskNobodyHoldsShouldLeaveNoRecord(): void
    {
        $task = $this->task();

        $this->delete($this->assignee($task));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->assigneeChanges($task));
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
        self::assertEquals(
            [['old' => ['assignee_id' => $this->colleague->id()], 'new' => ['assignee_id' => null]]],
            $this->assigneeChanges($task),
        );
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
        $cancelled = $this->task($this->colleague, TaskStatus::CANCELLED);
        $ofTheOwner = $this->task($this->user);
        $elsewhere = $this->task($this->colleague, workspace: WorkspaceFactory::new()->withMembers([[$this->colleague, WorkspaceRole::MEMBER]])->create(['owner' => $this->user]));

        $this->delete($this->member($this->colleague));
        self::assertResponseStatusCodeSame(204);

        self::assertNull($this->assigneeOf($inProgress));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($completed));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($cancelled));
        self::assertSame((string) $this->user->id(), $this->assigneeOf($ofTheOwner));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($elsewhere));

        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $inProgress->getId()->toRfc4122());
        self::assertSame($this->user->id(), $auditLogs[0]->getActorId());
        self::assertEquals(['old' => ['assignee_id' => $this->colleague->id()], 'new' => ['assignee_id' => null]], $auditLogs[0]->getAttributeChanges());
    }

    #[Test]
    public function reassignShouldHandUnfinishedTasksOfOneMemberOverToAnother(): void
    {
        $inProgress = $this->task($this->colleague, TaskStatus::IN_PROGRESS);
        $completed = $this->task($this->colleague, TaskStatus::COMPLETED);
        $ofTheOwner = $this->task($this->user);
        $elsewhere = $this->task($this->colleague, workspace: WorkspaceFactory::new()->withMembers([[$this->colleague, WorkspaceRole::MEMBER]])->create(['owner' => $this->user]));

        $data = $this->jsonData($this->post($this->reassign(), ['from' => $this->colleague->id(), 'to' => $this->user->id()]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$inProgress->getId()->toRfc4122()], array_column($data, 'id'));
        self::assertSame((string) $this->user->id(), $this->assigneeOf($inProgress));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($completed));
        self::assertSame((string) $this->user->id(), $this->assigneeOf($ofTheOwner));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($elsewhere));

        $auditLogs = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $inProgress->getId()->toRfc4122());
        self::assertSame($this->user->id(), $auditLogs[0]->getActorId());
        self::assertEquals(['old' => ['assignee_id' => $this->colleague->id()], 'new' => ['assignee_id' => $this->user->id()]], $auditLogs[0]->getAttributeChanges());
    }

    #[Test]
    public function memberShouldBeAbleToHandTasksOver(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::MEMBER]])->create(['owner' => $this->colleague]);
        $task = $this->task($this->user, workspace: $workspace);

        $this->post($this->reassign($workspace), ['from' => $this->user->id(), 'to' => $this->colleague->id()]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($task));
    }

    #[Test]
    public function reassignWhenNothingIsAssignedShouldChangeNothing(): void
    {
        $data = $this->jsonData($this->post($this->reassign(), ['from' => $this->colleague->id(), 'to' => $this->user->id()]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $data);
    }

    #[Test]
    #[TestWith(['viewer'])]
    #[TestWith(['stranger'])]
    #[TestWith(['the same user'])]
    public function tasksShouldBeHandedOverOnlyToSomeoneElseWhoCanWork(string $to): void
    {
        $viewer = UserFactory::createOne();
        $workspace = WorkspaceFactory::new()
            ->withMembers([[$this->colleague, WorkspaceRole::MEMBER], [$viewer, WorkspaceRole::VIEWER]])
            ->create(['owner' => $this->user]);
        $task = $this->task($this->colleague, workspace: $workspace);

        $this->post($this->reassign($workspace), ['from' => $this->colleague->id(), 'to' => match ($to) {
            'viewer' => $viewer->id(),
            'stranger' => UserFactory::createOne()->id(),
            'the same user' => $this->colleague->id(),
        }]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($task));
    }

    #[Test]
    #[TestWith([['from' => 5]])]
    #[TestWith([['to' => 7]])]
    #[TestWith([['from' => 0, 'to' => 7]])]
    #[TestWith([['from' => 5, 'to' => 'someone']])]
    public function reassignWithAnInvalidBodyShouldReturn422(array $body): void
    {
        $this->post($this->reassign(), $body);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function reassignShouldHandOverEverythingOrNothing(): void
    {
        $first = $this->task($this->colleague);
        $second = $this->task($this->colleague, TaskStatus::IN_PROGRESS);
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->post($this->reassign(), ['from' => $this->colleague->id(), 'to' => $this->user->id()]);
        self::assertResponseStatusCodeSame(500);

        static::getContainer()->get(AuditLogFailureToggle::class)->disable();
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($first));
        self::assertSame((string) $this->colleague->id(), $this->assigneeOf($second));
    }

    #[Test]
    public function viewerShouldNotHandTasksOver(): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, WorkspaceRole::VIEWER]])->create(['owner' => $this->colleague]);

        $this->post($this->reassign($workspace), ['from' => $this->colleague->id(), 'to' => $this->colleague->id()]);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function reassignInAWorkspaceOfStrangersShouldReturn404(): void
    {
        $this->post($this->reassign(WorkspaceFactory::createOne()), ['from' => $this->colleague->id(), 'to' => $this->user->id()]);

        self::assertResponseStatusCodeSame(404);
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
