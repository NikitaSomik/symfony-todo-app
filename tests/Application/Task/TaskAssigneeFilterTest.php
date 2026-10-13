<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Enum\TaskStatus;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class TaskAssigneeFilterTest extends ApiTestCase
{
    private User $user;
    private User $colleague;
    private Workspace $workspace;
    private string $tasks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->colleague = UserFactory::createOne();
        $this->actingAs($this->user);
        $this->workspace = WorkspaceFactory::new()->withMembers([[$this->colleague, WorkspaceRole::MEMBER]])->create(['owner' => $this->user]);
        $this->tasks = $this->route('api_workspace_task_get_all', ['id' => $this->workspace->getId()->toRfc4122()]);

        $this->task('Mine', $this->user);
        $this->task('Colleague', $this->colleague);
        $this->task('Nobody', null);
    }

    private function task(string $title, ?User $assignee, TaskStatus $status = TaskStatus::TODO, ?Workspace $workspace = null): void
    {
        TaskFactory::createOne([
            'workspace' => $workspace ?? $this->workspace,
            'title' => $title,
            'assigneeId' => $assignee?->getId(),
            'status' => $status,
        ]);
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $response = $this->get($this->tasks.$query);
        self::assertResponseIsSuccessful();

        $titles = array_map(static fn (array $task): string => $task['attributes']['title'], $this->jsonData($response));
        sort($titles);

        return $titles;
    }

    #[Test]
    public function getAllWhenAssigneeIsMeShouldReturnTheTasksOfTheUserAsking(): void
    {
        self::assertSame(['Mine'], $this->titles('?filter[assignee]=me'));
    }

    #[Test]
    public function getAllWhenAssigneeIsNoneShouldReturnTheTasksNobodyHolds(): void
    {
        self::assertSame(['Nobody'], $this->titles('?filter[assignee]=none'));
    }

    #[Test]
    public function getAllWhenAssigneeIsAnIdShouldReturnTheTasksOfThatUser(): void
    {
        self::assertSame(['Colleague'], $this->titles('?filter[assignee]='.$this->colleague->getId()));
    }

    #[Test]
    public function getAllWithoutAssigneeShouldReturnEveryTask(): void
    {
        self::assertSame(['Colleague', 'Mine', 'Nobody'], $this->titles(''));
    }

    #[Test]
    public function getAllShouldCombineAssigneeWithOtherFilters(): void
    {
        $this->task('Mine, done', $this->user, TaskStatus::COMPLETED);

        self::assertSame(['Mine, done'], $this->titles('?filter[assignee]=me&filter[status]=completed'));
    }

    #[Test]
    public function getAllWhenFilteredByAssigneeShouldCountOnlyTheMatchingTasks(): void
    {
        $this->task('Mine too', $this->user);

        $response = $this->get($this->tasks.'?filter[assignee]=me&page[size]=1');

        self::assertSame(2, $this->json($response)['meta']['page']['total']);
    }

    #[Test]
    public function getAllWhenAssigneeIsMeShouldLeaveOutTheTasksOfAnotherWorkspace(): void
    {
        $this->task('Mine elsewhere', $this->user, workspace: WorkspaceFactory::createOne(['owner' => $this->user]));

        self::assertSame(['Mine'], $this->titles('?filter[assignee]=me'));
    }

    #[Test]
    public function getAllWhenAssigneeIsNotAMemberShouldReturnNoTask(): void
    {
        self::assertSame([], $this->titles('?filter[assignee]='.UserFactory::createOne()->getId()));
    }

    #[Test]
    #[TestWith(['abc'])]
    #[TestWith(['ME'])]
    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['1.5'])]
    #[TestWith(['1000000000'])]
    #[TestWith(['99999999999999999999'])]
    #[TestWith(['me%0A'])]
    #[TestWith(['none%0A'])]
    #[TestWith(['1%0A'])]
    #[TestWith(['%201'])]
    #[TestWith(['%2B1'])]
    public function getAllWhenAssigneeIsInvalidShouldReturn422(string $assignee): void
    {
        $response = $this->get($this->tasks.'?filter[assignee]='.$assignee);
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Use "me", "none" or the id of a user.', $json['errors'][0]['detail']);
        self::assertSame(['parameter' => 'filter[assignee]'], $json['errors'][0]['source']);
    }
}
