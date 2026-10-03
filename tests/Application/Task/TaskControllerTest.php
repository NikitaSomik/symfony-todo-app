<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use App\Shared\AuditLog\Repository\AuditLogRepository;
use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class TaskControllerTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    /**
     * @return TaskStatusChange[]
     */
    private function statusChangesForTask(Task $task): array
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get(ManagerRegistry::class);

        return $registry->getRepository(TaskStatusChange::class)->findBy(
            ['task' => $task],
            ['changedAt' => 'ASC', 'id' => 'ASC'],
        );
    }

    private function taskId(Task $task): string
    {
        return $task->getId()->toRfc4122();
    }

    /** @return list<string> */
    private function titles(Response $response): array
    {
        return array_map(static fn (array $task): string => $task['attributes']['title'], $this->jsonData($response));
    }

    /**
     * Most relevant first: "Milk run" (title and description), "Milk plan" (title), "Workout" (description).
     * Created in none of the orders a sort by relevance produces.
     *
     * @param array<string, mixed> $attributes
     */
    private function createTasksOfDifferentRelevanceToMilk(array $attributes = []): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan', 'description' => 'Weekly groceries', ...$attributes]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Workout', 'description' => 'Drink milk after gym', ...$attributes]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk run', 'description' => 'Get milk for the week', ...$attributes]);
    }

    private function missingTaskId(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    private function auditLogsForTask(Task $task): array
    {
        $repository = static::getContainer()->get(AuditLogRepository::class);

        return $repository->findForEntity(AuditLogEntityType::TASK, $this->taskId($task));
    }

    private function failAuditLogEventDispatching(): void
    {
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();
    }

    #[Test]
    public function getAllWhenNoTasksShouldReturnEmptyArray(): void
    {
        $response = $this->get($this->route('api_task_get_all'));
        $json = $this->json($response);

        self::assertResponseIsSuccessful();
        self::assertSame([], $json['data']);
        self::assertSame(1, $json['meta']['page']['current']);
        self::assertSame(20, $json['meta']['page']['size']);
        self::assertSame(0, $json['meta']['page']['total']);
        self::assertSame(1, $json['meta']['page']['last']);
        self::assertArrayNotHasKey('self', $json['links']);
        self::assertNull($json['links']['prev']);
        self::assertNull($json['links']['next']);
    }

    #[Test]
    public function getAllWhenTasksExistShouldReturnAll(): void
    {
        TaskFactory::createMany(3, ['user' => $this->user]);

        $response = $this->get($this->route('api_task_get_all'));
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $data);
    }

    #[Test]
    public function getAllShouldNotReturnOtherUsersTask(): void
    {
        $otherUser = UserFactory::createOne();
        TaskFactory::createOne(['user' => $otherUser]);
        TaskFactory::createMany(2, ['user' => $this->user]);

        $response = $this->get($this->route('api_task_get_all'));
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
    }

    #[Test]
    public function getAllShouldReturnPaginationLinks(): void
    {
        TaskFactory::createMany(25, ['user' => $this->user]);

        $response = $this->get('/api/v1/tasks?page[number]=2&page[size]=10');
        $json = $this->json($response);

        self::assertResponseIsSuccessful();
        self::assertCount(10, $json['data']);
        self::assertSame(2, $json['meta']['page']['current']);
        self::assertSame(10, $json['meta']['page']['size']);
        self::assertSame(25, $json['meta']['page']['total']);
        self::assertSame(3, $json['meta']['page']['last']);
        self::assertArrayNotHasKey('self', $json['links']);
        self::assertSame('/api/v1/tasks?page[number]=1&page[size]=10', $json['links']['first']);
        self::assertSame('/api/v1/tasks?page[number]=3&page[size]=10', $json['links']['last']);
        self::assertSame('/api/v1/tasks?page[number]=1&page[size]=10', $json['links']['prev']);
        self::assertSame('/api/v1/tasks?page[number]=3&page[size]=10', $json['links']['next']);
    }

    #[Test]
    public function getAllShouldFilterByStatus(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'status' => TaskStatus::TODO]);
        TaskFactory::createOne(['user' => $this->user, 'status' => TaskStatus::COMPLETED]);

        $response = $this->get('/api/v1/tasks?filter[status]=completed');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame('completed', $data[0]['attributes']['status']);
    }

    /**
     * The tests on order create their tasks so that neither the creation order nor its reverse matches
     * the expected one: a sort that falls back to the id, the last sort key, then fails.
     */
    #[Test]
    #[TestWith(['', ['Newest', 'Middle', 'Oldest']])]
    #[TestWith(['?direction=asc', ['Oldest', 'Middle', 'Newest']])]
    public function getAllWithoutSortShouldOrderByCreationTime(string $query, array $expectedTitles): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Middle', 'createdAt' => new \DateTimeImmutable('2026-04-01 10:01:00')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Newest', 'createdAt' => new \DateTimeImmutable('2026-04-01 10:02:00')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Oldest', 'createdAt' => new \DateTimeImmutable('2026-04-01 10:00:00')]);

        $response = $this->get('/api/v1/tasks'.$query);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedTitles, $this->titles($response));
    }

    #[Test]
    public function getAllShouldSortByStatus(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'In progress', 'status' => TaskStatus::IN_PROGRESS]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Todo', 'status' => TaskStatus::TODO]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Completed', 'status' => TaskStatus::COMPLETED]);

        $response = $this->get('/api/v1/tasks?sort=status&direction=asc');

        self::assertResponseIsSuccessful();
        self::assertSame(['Completed', 'In progress', 'Todo'], $this->titles($response));
    }

    #[Test]
    public function getAllShouldSortByDueDateAscendingWithNullsLast(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Later', 'dueDate' => new \DateTimeImmutable('2026-04-02')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'No deadline', 'dueDate' => null]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Sooner', 'dueDate' => new \DateTimeImmutable('2026-04-01')]);

        $response = $this->get('/api/v1/tasks?sort=due_date&direction=asc');

        self::assertResponseIsSuccessful();
        self::assertSame(['Sooner', 'Later', 'No deadline'], $this->titles($response));
    }

    /**
     * Tasks that tie on the sort field must still come in one fixed order, or paging through them
     * shows some twice and skips others. The id is the last sort key and follows the requested
     * direction; UUIDv7 grows with creation time, so descending puts the newest first.
     */
    #[Test]
    #[TestWith(['desc'])]
    #[TestWith(['asc'])]
    public function getAllShouldBreakSortTiesInTheRequestedDirection(string $direction): void
    {
        $tasks = TaskFactory::createMany(3, ['user' => $this->user, 'status' => TaskStatus::TODO]);
        $created = array_map(fn (Task $task): string => $this->taskId($task), $tasks);
        $expected = 'asc' === $direction ? $created : array_reverse($created);

        $pages = [];
        foreach ([1, 2, 3] as $number) {
            $pages[] = $this->jsonData($this->get('/api/v1/tasks?sort=status&direction='.$direction.'&page[size]=1&page[number]='.$number))[0]['id'];
        }

        self::assertSame($expected, $pages);
    }

    #[Test]
    public function getAllPastTheLastPageShouldPointBackToTheLastPage(): void
    {
        TaskFactory::createMany(3, ['user' => $this->user]);

        $json = $this->json($this->get('/api/v1/tasks?page[number]=5&page[size]=2'));

        self::assertSame([], $json['data']);
        self::assertSame('/api/v1/tasks?page[number]=2&page[size]=2', $json['links']['prev']);
        self::assertSame('/api/v1/tasks?page[number]=2&page[size]=2', $json['links']['last']);
        self::assertNull($json['links']['next']);
    }

    /** Relevance is not a sort field: a search without a sort is ordered by it. */
    #[Test]
    #[TestWith(['title'])]
    #[TestWith(['relevance'])]
    #[TestWith([''])]
    public function getAllWhenSortFieldIsNotSupportedShouldReturn422(string $sort): void
    {
        $response = $this->get('/api/v1/tasks?sort='.$sort);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['parameter' => 'sort'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function getAllWhenSearchTermIsTooLongShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[search]='.str_repeat('a', 101));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['parameter' => 'filter[search]'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function getAllShouldFilterByDueDateRange(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Before range', 'dueDate' => new \DateTimeImmutable('2026-03-31')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Inside range', 'dueDate' => new \DateTimeImmutable('2026-04-02')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'After range', 'dueDate' => new \DateTimeImmutable('2026-04-06')]);

        $response = $this->get('/api/v1/tasks?filter[due_from]=2026-04-01&filter[due_to]=2026-04-05');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame('Inside range', $data[0]['attributes']['title']);
    }

    #[Test]
    public function getAllShouldSearchByTitleAndDescription(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk', 'description' => null]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Workout', 'description' => 'Drink milk after gym']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Read book', 'description' => 'Evening routine']);

        $response = $this->get('/api/v1/tasks?filter[search]=milk');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
    }

    #[Test]
    public function getAllShouldSupportMultiWordSearch(): void
    {
        TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Review PostgreSQL full-text search',
            'description' => 'Prepare implementation notes',
        ]);
        TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Review PostgreSQL indexes',
            'description' => 'Compare search options later',
        ]);

        $response = $this->get('/api/v1/tasks?filter[search]=postgresql search');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
        self::assertSame('Review PostgreSQL full-text search', $data[0]['attributes']['title']);
    }

    #[Test]
    #[TestWith(['task', 'Review tasks'])]
    #[TestWith(['review', 'Reviewed the budget'])]
    #[TestWith(['run', 'Running shoes'])]
    #[TestWith(['deploy', 'Deployment checklist'])]
    public function getAllShouldFindOtherFormsOfTheSearchedWord(string $search, string $title): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => $title]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy bread']);

        $response = $this->get('/api/v1/tasks?filter[search]='.$search);
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame($title, $data[0]['attributes']['title']);
    }

    #[Test]
    public function getAllShouldIgnoreStopWordsInTheSearchTerm(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Call bank']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Call mom']);

        $response = $this->get('/api/v1/tasks?filter[search]=call the bank');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame('Call bank', $data[0]['attributes']['title']);
    }

    #[Test]
    public function getAllWhenNoTaskMatchesTheSearchShouldReturnEmptyArray(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk']);

        $response = $this->get('/api/v1/tasks?filter[search]=coffee');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->jsonData($response));
    }

    #[Test]
    public function getAllShouldNotFindOtherUsersTasks(): void
    {
        TaskFactory::createOne(['user' => UserFactory::createOne(), 'title' => 'Buy milk']);

        $response = $this->get('/api/v1/tasks?filter[search]=milk');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->jsonData($response));
    }

    #[Test]
    public function getAllWhenSearchTermIsBlankShouldNotFilter(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Read book']);

        $response = $this->get('/api/v1/tasks?filter[search]='.urlencode('   '));

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->jsonData($response));
    }

    #[Test]
    #[TestWith(['!@#&|:*()', []])]
    #[TestWith(['"unclosed', []])]
    #[TestWith(['milk & | ! bread', []])]
    #[TestWith(['milk & | !', ['Buy milk']])]
    public function getAllWhenSearchTermHasQuerySyntaxCharactersShouldNotFail(string $search, array $expectedTitles): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk']);

        $response = $this->get('/api/v1/tasks?filter[search]='.urlencode($search));
        $titles = $this->titles($response);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedTitles, $titles);
    }

    #[Test]
    #[TestWith(['the'])]
    #[TestWith(['to do'])]
    public function getAllWhenSearchTermHasOnlyStopWordsShouldFindNothing(string $search): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Things to do in the morning']);

        $response = $this->get('/api/v1/tasks?filter[search]='.$search);
        $json = $this->json($response);

        self::assertResponseIsSuccessful();
        self::assertSame([], $json['data']);
        self::assertSame(0, $json['meta']['page']['total']);
    }

    #[Test]
    #[TestWith(['milk -sell', ['Buy milk']])]
    #[TestWith(['bread or sell', ['Buy bread', 'Sell milk']])]
    #[TestWith(['"buy milk"', ['Buy milk']])]
    public function getAllShouldSupportWebSearchSyntax(string $search, array $expectedTitles): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy bread']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Sell milk', 'description' => 'Then buy more']);

        $response = $this->get('/api/v1/tasks?filter[search]='.urlencode($search));
        $titles = $this->titles($response);
        sort($titles);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedTitles, $titles);
    }

    #[Test]
    public function getAllShouldRankTitleMatchesHigherThanDescriptionMatches(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan', 'description' => 'Weekly groceries']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Workout', 'description' => 'Drink milk after gym']);

        $response = $this->get('/api/v1/tasks?filter[search]=milk');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
        self::assertSame('Milk plan', $data[0]['attributes']['title']);
        self::assertSame('Workout', $data[1]['attributes']['title']);
    }

    #[Test]
    public function getAllWhenSearchingShouldSortByTheChosenField(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan', 'description' => null, 'dueDate' => new \DateTimeImmutable('2026-04-02')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk shake', 'description' => null, 'dueDate' => new \DateTimeImmutable('2026-04-03')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Workout', 'description' => 'Drink milk after gym', 'dueDate' => new \DateTimeImmutable('2026-04-01')]);

        $response = $this->get('/api/v1/tasks?filter[search]=milk&sort=due_date&direction=asc');

        self::assertResponseIsSuccessful();
        self::assertSame(['Workout', 'Milk plan', 'Milk shake'], $this->titles($response));
    }

    #[Test]
    public function getAllWhenSearchingShouldBreakSortTiesByRelevance(): void
    {
        $this->createTasksOfDifferentRelevanceToMilk(['status' => TaskStatus::TODO]);

        $response = $this->get('/api/v1/tasks?filter[search]=milk&sort=status');

        self::assertResponseIsSuccessful();
        self::assertSame(['Milk run', 'Milk plan', 'Workout'], $this->titles($response));
    }

    #[Test]
    public function getAllWhenSearchingWithoutSortShouldApplyDirectionToRelevance(): void
    {
        $this->createTasksOfDifferentRelevanceToMilk();

        $response = $this->get('/api/v1/tasks?filter[search]=milk&direction=asc');

        self::assertResponseIsSuccessful();
        self::assertSame(['Workout', 'Milk plan', 'Milk run'], $this->titles($response));
    }

    #[Test]
    public function getAllShouldHandleNullableDescriptionInSearchResults(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk', 'description' => null]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy bread', 'description' => null]);

        $response = $this->get('/api/v1/tasks?filter[search]=milk');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame('Buy milk', $data[0]['attributes']['title']);
        self::assertNull($data[0]['attributes']['description']);
    }

    #[Test]
    public function getAllShouldCombineFullTextSearchWithStatusFilter(): void
    {
        TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk',
            'status' => TaskStatus::COMPLETED,
        ]);
        TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk tomorrow',
            'status' => TaskStatus::TODO,
        ]);

        $response = $this->get('/api/v1/tasks?filter[search]=milk&filter[status]=completed');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $data);
        self::assertSame('Buy milk', $data[0]['attributes']['title']);
        self::assertSame('completed', $data[0]['attributes']['status']);
    }

    #[Test]
    public function getAllShouldPaginateSearchResults(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan A']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan B']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan C']);

        $response = $this->get('/api/v1/tasks?filter[search]=milk&page[number]=2&page[size]=2');
        $json = $this->json($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $json['data']);
        self::assertSame(2, $json['meta']['page']['current']);
        self::assertSame(2, $json['meta']['page']['size']);
        self::assertSame(3, $json['meta']['page']['total']);
        self::assertSame(2, $json['meta']['page']['last']);
        self::assertSame('/api/v1/tasks?filter[search]=milk&page[number]=1&page[size]=2', $json['links']['first']);
        self::assertSame('/api/v1/tasks?filter[search]=milk&page[number]=2&page[size]=2', $json['links']['last']);
        self::assertSame('/api/v1/tasks?filter[search]=milk&page[number]=1&page[size]=2', $json['links']['prev']);
        self::assertNull($json['links']['next']);
    }

    #[Test]
    public function createWhenValidDataShouldReturn201(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function createShouldPointToTheNewTaskWithTheLocationHeader(): void
    {
        $response = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);

        self::assertSame(
            $this->route('api_task_get', ['id' => $this->jsonData($response)['id']]),
            $response->headers->get('Location'),
        );
    }

    #[Test]
    public function createWhenValidDataShouldReturnTask(): void
    {
        $response = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertSame('tasks', $data['type']);
        self::assertSame('Buy milk', $attributes['title']);
        self::assertSame(TaskStatus::TODO->value, $attributes['status']);
        self::assertNull($attributes['description']);
        self::assertNull($attributes['cancellation_reason']);
        self::assertNull($attributes['due_date']);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('created_at', $attributes);
        self::assertArrayHasKey('updated_at', $attributes);
    }

    #[Test]
    public function createWhenAllFieldsProvidedShouldReturnTask(): void
    {
        $response = $this->post($this->route('api_task_create'), [
            'title' => 'Buy milk',
            'description' => '2 liters',
            'due_date' => '2026-04-01',
        ]);

        self::assertSame([
            'title' => 'Buy milk',
            'description' => '2 liters',
            'status' => 'todo',
            'due_date' => '2026-04-01',
        ], array_intersect_key($this->jsonAttributes($response), array_flip(['title', 'description', 'status', 'due_date'])));
    }

    #[Test]
    public function createWhenTitleIsEmptyShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createShouldStartInTodoWhateverStatusTheBodyAsksFor(): void
    {
        $response = $this->post($this->route('api_task_create'), [
            'title' => 'Deprecated task',
            'status' => 'cancelled',
            'cancellation_reason' => 'No longer needed',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([
            'status' => 'todo',
            'cancellation_reason' => null,
        ], array_intersect_key($this->jsonAttributes($response), array_flip(['status', 'cancellation_reason'])));
    }

    #[Test]
    public function createWhenDescriptionIsTooShortShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Test', 'description' => 'ab']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createWhenDescriptionIsTooLongShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Test', 'description' => str_repeat('a', 2001)]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createWhenDueDateIsInvalidShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Test', 'due_date' => 'tomorrow']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function getWhenTaskExistsShouldReturnTask(): void
    {
        $task = TaskFactory::createOne([
            'title' => 'Buy milk',
            'user' => $this->user,
            'dueDate' => new \DateTimeImmutable('2026-04-01'),
        ]);

        $response = $this->get($this->route('api_task_get', ['id' => $this->taskId($task)]));
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertResponseIsSuccessful();
        self::assertSame((string) $task->getId(), $data['id']);
        self::assertSame('Buy milk', $attributes['title']);
        self::assertArrayHasKey('description', $attributes);
        self::assertArrayHasKey('status', $attributes);
        self::assertArrayHasKey('cancellation_reason', $attributes);
        self::assertSame('2026-04-01', $attributes['due_date']);
        self::assertArrayHasKey('created_at', $attributes);
        self::assertArrayHasKey('updated_at', $attributes);
    }

    #[Test]
    public function getWhenTaskNotFoundShouldReturn404(): void
    {
        $this->get($this->route('api_task_get', ['id' => $this->missingTaskId()]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function getWhenTaskBelongsToAnotherUserShouldReturn404(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->get($this->route('api_task_get', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function getWhenTaskBelongsToAnotherUserShouldAnswerLikeAMissingTask(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $missing = $this->json($this->get($this->route('api_task_get', ['id' => $this->missingTaskId()])));
        $foreign = $this->json($this->get($this->route('api_task_get', ['id' => $this->taskId($task)])));

        self::assertSame($missing, $foreign);
    }

    #[Test]
    public function updateWhenValidDataShouldReturnUpdatedTask(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Updated title',
            'due_date' => '2026-04-03',
        ]);
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertResponseIsSuccessful();
        self::assertSame((string) $task->getId(), $data['id']);
        self::assertSame('Updated title', $attributes['title']);
        self::assertSame('2026-04-03', $attributes['due_date']);
    }

    #[Test]
    public function updateShouldLeaveTheStatusAloneWhateverStatusTheBodyAsksFor(): void
    {
        $task = TaskFactory::new()->inProgress()->create(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Renamed task',
            'status' => TaskStatus::COMPLETED->value,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('in_progress', $this->jsonAttributes($response)['status']);
        self::assertSame([], $this->statusChangesForTask($task));
    }

    #[Test]
    public function updateWhenTitleIsEmptyShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), ['title' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenDescriptionIsTooShortShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), ['title' => 'Test', 'description' => 'ab']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenDescriptionIsTooLongShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Test',
            'description' => str_repeat('a', 2001),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenDueDateIsInvalidShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Test',
            'due_date' => 'tomorrow',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function getAllWhenDueFromIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_from]=tomorrow');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value is not a valid date. Use the YYYY-MM-DD format.', $json['errors'][0]['detail']);
        self::assertSame(['parameter' => 'filter[due_from]'], $json['errors'][0]['source']);
    }

    #[Test]
    public function getAllWhenDueRangeIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_from]=2026-04-05&filter[due_to]=2026-04-01');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value should be greater than or equal to due_from.', $json['errors'][0]['detail']);
    }

    #[Test]
    public function getAllWhenDueToIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_to]=tomorrow');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value is not a valid date. Use the YYYY-MM-DD format.', $json['errors'][0]['detail']);
    }

    #[Test]
    public function updateWhenTaskNotFoundShouldReturn404(): void
    {
        $this->put($this->route('api_task_update', ['id' => $this->missingTaskId()]), ['title' => 'Test']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function updateWhenTaskBelongsToAnotherUserShouldReturn404(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), ['title' => 'Hacked']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function updateWhenTaskBelongsToAnotherUserAndBodyIsInvalidShouldReturn404(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), ['title' => '']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteWhenTaskExistsShouldReturn204(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->delete($this->route('api_task_delete', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function deleteWhenTaskDeletedShouldReturn404OnGet(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);
        $taskId = $this->taskId($task);

        $this->delete($this->route('api_task_delete', ['id' => $taskId]));
        $this->get($this->route('api_task_get', ['id' => $taskId]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteWhenTaskNotFoundShouldReturn404(): void
    {
        $this->delete($this->route('api_task_delete', ['id' => $this->missingTaskId()]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteWhenTaskBelongsToAnotherUserShouldReturn404(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->delete($this->route('api_task_delete', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function createWhenValidDataShouldCreateAuditLogEntry(): void
    {
        $response = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);
        $taskId = $this->jsonData($response)['id'];

        $activities = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $taskId);

        self::assertCount(1, $activities);
        self::assertSame('created', $activities[0]->getAction()->value);
        self::assertSame('Created task "Buy milk"', $activities[0]->getMessage());
        self::assertSame($this->user->getId(), $activities[0]->getUser()?->getId());
        self::assertSame('Buy milk', $activities[0]->getMetadata()['entity_data']['title']);
    }

    #[Test]
    public function updateWhenTaskIsChangedShouldCreateAuditLogEntryForEachChangedField(): void
    {
        $task = TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk',
            'description' => null,
            'status' => TaskStatus::TODO,
            'due_date' => null,
        ]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Buy almond milk',
            'description' => null,
            'due_date' => '2026-04-03',
        ]);

        self::assertResponseIsSuccessful();

        $auditLogs = $this->auditLogsForTask($task);

        self::assertCount(2, $auditLogs);

        self::assertSame('updated', $auditLogs[0]->getAction()->value);
        self::assertSame('Updated task title for "Buy almond milk"', $auditLogs[0]->getMessage());
        self::assertSame('Buy milk', $auditLogs[0]->getAttributeChanges()['old']['title']);
        self::assertSame('Buy almond milk', $auditLogs[0]->getAttributeChanges()['new']['title']);
        self::assertCount(1, $auditLogs[0]->getAttributeChanges()['old']);

        self::assertSame('updated', $auditLogs[1]->getAction()->value);
        self::assertSame('Updated task due date for "Buy almond milk"', $auditLogs[1]->getMessage());
        self::assertSame(null, $auditLogs[1]->getAttributeChanges()['old']['due_date']);
        self::assertSame('2026-04-03', $auditLogs[1]->getAttributeChanges()['new']['due_date']);
        self::assertCount(1, $auditLogs[1]->getAttributeChanges()['old']);
    }

    #[Test]
    public function getAuditLogsShouldReturnTaskHistory(): void
    {
        $createResponse = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);
        $taskId = $this->jsonData($createResponse)['id'];

        $this->put($this->route('api_task_update', ['id' => $taskId]), [
            'title' => 'Buy almond milk',
            'description' => null,
            'due_date' => null,
        ]);

        $response = $this->get($this->route('api_task_get_audit_logs', ['id' => $taskId]));
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
        self::assertSame('created', $data[0]['attributes']['action']);
        self::assertSame('Created task "Buy milk"', $data[0]['attributes']['message']);
        self::assertSame('Buy milk', $data[0]['attributes']['metadata']['entity_data']['title']);
        self::assertSame('updated', $data[1]['attributes']['action']);
        self::assertSame('Updated task title for "Buy almond milk"', $data[1]['attributes']['message']);
        self::assertSame('Buy almond milk', $data[1]['attributes']['attribute_changes']['new']['title']);
    }

    #[Test]
    public function getAuditLogsWhenTaskBelongsToAnotherUserShouldReturn404(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->get($this->route('api_task_get_audit_logs', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function getAuditLogsShouldLinkTheUserAndTheTaskAsRelationships(): void
    {
        $taskId = $this->jsonData($this->post($this->route('api_task_create'), ['title' => 'Buy milk']))['id'];

        $entry = $this->jsonData($this->get($this->route('api_task_get_audit_logs', ['id' => $taskId])))[0];

        self::assertSame([
            'user' => ['data' => ['type' => 'users', 'id' => (string) $this->user->getId()]],
            'entity' => ['data' => ['type' => 'tasks', 'id' => $taskId]],
        ], $entry['relationships']);
        self::assertArrayNotHasKey('user_id', $entry['attributes']);
        self::assertArrayNotHasKey('entity_id', $entry['attributes']);
        self::assertArrayNotHasKey('entity_type', $entry['attributes']);
    }

    #[Test]
    public function deleteWhenTaskExistsShouldCreateDeletedAuditLogEntry(): void
    {
        $task = TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk',
        ]);
        $taskId = $this->taskId($task);

        $this->delete($this->route('api_task_delete', ['id' => $taskId]));

        self::assertResponseStatusCodeSame(204);

        $activities = static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $taskId);

        self::assertCount(1, $activities);
        self::assertSame('deleted', $activities[0]->getAction()->value);
        self::assertSame('Deleted task "Buy milk"', $activities[0]->getMessage());
        self::assertSame('Buy milk', $activities[0]->getMetadata()['entity_data']['title']);
    }

    #[Test]
    public function createShouldRollbackTaskCreationWhenEventDispatchFails(): void
    {
        $this->failAuditLogEventDispatching();

        $response = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);

        self::assertResponseStatusCodeSame(500);

        $tasks = static::getContainer()->get(TaskRepository::class)->findBy([
            'user' => $this->user,
            'title' => 'Buy milk',
        ]);

        self::assertSame([], $tasks);
    }

    #[Test]
    public function updateShouldRollbackChangesWhenEventDispatchFails(): void
    {
        $task = TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk',
            'description' => null,
            'status' => TaskStatus::TODO,
            'dueDate' => null,
        ]);
        $taskId = $this->taskId($task);

        $this->failAuditLogEventDispatching();

        $response = $this->put($this->route('api_task_update', ['id' => $taskId]), [
            'title' => 'Buy almond milk',
            'description' => null,
            'due_date' => '2026-04-03',
        ]);

        self::assertResponseStatusCodeSame(500);

        $reloadedTask = static::getContainer()->get(TaskRepository::class)->find($taskId);

        self::assertInstanceOf(Task::class, $reloadedTask);
        self::assertSame('Buy milk', $reloadedTask->getTitle());
        self::assertSame(TaskStatus::TODO, $reloadedTask->getStatus());
        self::assertNull($reloadedTask->getDueDate());
        self::assertSame([], $this->statusChangesForTask($reloadedTask));
        self::assertSame([], $this->auditLogsForTask($reloadedTask));
    }

    #[Test]
    public function deleteShouldRollbackRemovalWhenEventDispatchFails(): void
    {
        $task = TaskFactory::createOne([
            'user' => $this->user,
            'title' => 'Buy milk',
        ]);
        $taskId = $this->taskId($task);

        $this->failAuditLogEventDispatching();

        $response = $this->delete($this->route('api_task_delete', ['id' => $taskId]));

        self::assertResponseStatusCodeSame(500);
        self::assertInstanceOf(Task::class, static::getContainer()->get(TaskRepository::class)->find($taskId));
        self::assertSame([], static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $taskId));
    }

    #[Test]
    #[TestWith(['todo', ['self', 'start', 'cancel']])]
    #[TestWith(['blocked', ['self', 'unblock', 'cancel']])]
    #[TestWith(['completed', ['self']])]
    public function getShouldLinkOnlyTheTransitionsTheStatusAllows(string $status, array $expectedLinks): void
    {
        $task = TaskFactory::createOne(['user' => $this->user, 'status' => TaskStatus::from($status)]);

        $response = $this->get($this->route('api_task_get', ['id' => $this->taskId($task)]));

        self::assertSame($expectedLinks, array_keys($this->jsonData($response)['links']));
    }

    #[Test]
    public function getAllShouldLinkEveryTask(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $data = $this->jsonData($this->get('/api/v1/tasks'));

        self::assertSame('/api/v1/tasks/'.$this->taskId($task).'/start', $data[0]['links']['start']);
    }
}
