<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Contract\TaskAssigned;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use App\Tests\Support\PublishedEvents;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;

final class TaskAssignedTest extends ApiTestCase
{
    private User $user;
    private User $colleague;
    private User $viewer;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->colleague = UserFactory::createOne();
        $this->viewer = UserFactory::createOne();
        $this->actingAs($this->user);
        $this->workspace = WorkspaceFactory::new()
            ->withMembers([[$this->colleague, WorkspaceRole::MEMBER], [$this->viewer, WorkspaceRole::VIEWER]])
            ->create(['owner' => $this->user]);
    }

    private function task(?User $assignee = null, TaskStatus $status = TaskStatus::TODO): Task
    {
        return TaskFactory::createOne([
            'workspace' => $this->workspace,
            'assigneeId' => $assignee?->id(),
            'status' => $status,
            'cancellationReason' => TaskStatus::CANCELLED === $status ? 'Outdated' : null,
        ]);
    }

    private function assign(Task $task, User $assignee): void
    {
        $this->put($this->route('api_task_assign', ['id' => $task->getId()->toRfc4122()]), ['user_id' => $assignee->id()]);
    }

    private function reassign(User $from, User $to): void
    {
        $this->post($this->route('api_workspace_task_reassign', ['id' => $this->workspace->getId()->toRfc4122()]), ['from' => $from->id(), 'to' => $to->id()]);
    }

    /** @return list<TaskAssigned> */
    private function published(): array
    {
        return static::getContainer()->get(PublishedEvents::class)->of(TaskAssigned::class);
    }

    #[Test]
    public function assigningShouldAnnounceWhoGotTheTaskAndFromWhom(): void
    {
        $task = $this->task();

        $this->assign($task, $this->colleague);
        self::assertResponseStatusCodeSame(200);

        self::assertEquals(
            [new TaskAssigned($task->getId(), $this->workspace->getId(), assigneeId: $this->colleague->id(), actorId: $this->user->id())],
            $this->published(),
        );
    }

    #[Test]
    public function assigningSomeoneElseShouldAnnounceTheNewAssignee(): void
    {
        $task = $this->task($this->colleague);

        $this->assign($task, $this->user);
        self::assertResponseStatusCodeSame(200);

        self::assertSame([$this->user->id()], array_map(static fn (TaskAssigned $event): int => $event->assigneeId, $this->published()));
    }

    #[Test]
    public function assigningTheSameAssigneeAgainShouldAnnounceNothing(): void
    {
        $task = $this->task($this->colleague);

        $this->assign($task, $this->colleague);
        self::assertResponseStatusCodeSame(200);

        self::assertSame([], $this->published());
    }

    #[Test]
    public function refusedAssignmentShouldAnnounceNothing(): void
    {
        $this->assign($this->task(), $this->viewer);
        self::assertResponseStatusCodeSame(422);

        $this->assign($this->task(status: TaskStatus::COMPLETED), $this->colleague);
        self::assertResponseStatusCodeSame(409);

        self::assertSame([], $this->published());
    }

    #[Test]
    public function assignmentThatIsRolledBackShouldAnnounceNothing(): void
    {
        $task = $this->task();
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->assign($task, $this->colleague);
        self::assertResponseStatusCodeSame(500);

        self::assertSame([], $this->published());
    }

    #[Test]
    public function takingATaskOffItsAssigneeShouldAnnounceNothing(): void
    {
        $task = $this->task($this->colleague);

        $this->delete($this->route('api_task_unassign', ['id' => $task->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(200);

        self::assertSame([], $this->published());
    }

    #[Test]
    public function handingWorkOverShouldAnnounceEveryTaskThatChangedHands(): void
    {
        $first = $this->task($this->colleague);
        $second = $this->task($this->colleague, TaskStatus::IN_PROGRESS);
        $this->task($this->colleague, TaskStatus::COMPLETED);

        $this->reassign($this->colleague, $this->user);
        self::assertResponseStatusCodeSame(200);

        $published = $this->published();
        $taskIds = array_map(static fn (TaskAssigned $event): string => $event->taskId->toRfc4122(), $published);
        sort($taskIds);
        $expected = [$first->getId()->toRfc4122(), $second->getId()->toRfc4122()];
        sort($expected);
        self::assertSame($expected, $taskIds);
        foreach ($published as $event) {
            self::assertSame($this->user->id(), $event->assigneeId);
            self::assertSame($this->user->id(), $event->actorId);
        }
    }

    #[Test]
    public function handOverThatIsRolledBackShouldAnnounceNothing(): void
    {
        $this->task($this->colleague);
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->reassign($this->colleague, $this->user);
        self::assertResponseStatusCodeSame(500);

        self::assertSame([], $this->published());
    }
}
