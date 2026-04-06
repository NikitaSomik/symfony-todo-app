<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\DataFixtures\UserFactory;
use App\Auth\Entity\User;
use App\Task\DataFixtures\TaskFactory;
use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use App\Task\Enum\TaskStatus;
use App\Tests\ApiTestCase;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
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

    private function missingTaskId(): string
    {
        return Uuid::v7()->toRfc4122();
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

    #[Test]
    public function getAllShouldSortByDueDateAscendingWithNullsLast(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'No deadline', 'dueDate' => null]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Later', 'dueDate' => new \DateTimeImmutable('2026-04-02')]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Sooner', 'dueDate' => new \DateTimeImmutable('2026-04-01')]);

        $response = $this->get('/api/v1/tasks?sort=due_date&direction=asc');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertSame('Sooner', $data[0]['attributes']['title']);
        self::assertSame('Later', $data[1]['attributes']['title']);
        self::assertSame('No deadline', $data[2]['attributes']['title']);
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

        $response = $this->get('/api/v1/tasks?search=milk');
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

        $response = $this->get('/api/v1/tasks?search=postgresql search');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
        self::assertSame('Review PostgreSQL full-text search', $data[0]['attributes']['title']);
    }

    #[Test]
    public function getAllShouldRankTitleMatchesHigherThanDescriptionMatches(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Milk plan', 'description' => 'Weekly groceries']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Workout', 'description' => 'Drink milk after gym']);

        $response = $this->get('/api/v1/tasks?search=milk');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $data);
        self::assertSame('Milk plan', $data[0]['attributes']['title']);
        self::assertSame('Workout', $data[1]['attributes']['title']);
    }

    #[Test]
    public function getAllShouldHandleNullableDescriptionInSearchResults(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy milk', 'description' => null]);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Buy bread', 'description' => null]);

        $response = $this->get('/api/v1/tasks?search=milk');
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

        $response = $this->get('/api/v1/tasks?search=milk&filter[status]=completed');
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

        $response = $this->get('/api/v1/tasks?search=milk&page[number]=2&page[size]=2');
        $json = $this->json($response);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $json['data']);
        self::assertSame(2, $json['meta']['page']['current']);
        self::assertSame(2, $json['meta']['page']['size']);
        self::assertSame(3, $json['meta']['page']['total']);
        self::assertSame(2, $json['meta']['page']['last']);
        self::assertSame('/api/v1/tasks?search=milk&page[number]=1&page[size]=2', $json['links']['first']);
        self::assertSame('/api/v1/tasks?search=milk&page[number]=2&page[size]=2', $json['links']['last']);
        self::assertSame('/api/v1/tasks?search=milk&page[number]=1&page[size]=2', $json['links']['prev']);
        self::assertNull($json['links']['next']);
    }

    #[Test]
    public function createWhenValidDataShouldReturn201(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);

        self::assertResponseStatusCodeSame(201);
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
            'status' => 'in_progress',
            'due_date' => '2026-04-01',
        ]);

        self::assertSame([
            'title' => 'Buy milk',
            'description' => '2 liters',
            'status' => 'in_progress',
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
    public function createWhenStatusIsInvalidShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Test', 'status' => 'invalid']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createWhenCancelledWithoutCancellationReasonShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), ['title' => 'Test', 'status' => 'cancelled']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createWhenCancellationReasonIsProvidedForNonCancelledStatusShouldReturn422(): void
    {
        $this->post($this->route('api_task_create'), [
            'title' => 'Test',
            'status' => 'todo',
            'cancellation_reason' => 'No longer needed',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function createWhenCancelledShouldReturnTaskWithCancellationReason(): void
    {
        $response = $this->post($this->route('api_task_create'), [
            'title' => 'Deprecated task',
            'status' => 'cancelled',
            'cancellation_reason' => 'No longer needed',
        ]);

        self::assertSame([
            'status' => 'cancelled',
            'cancellation_reason' => 'No longer needed',
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
    public function getWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->get($this->route('api_task_get', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function updateWhenValidDataShouldReturnUpdatedTask(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Updated title',
            'status' => 'completed',
            'due_date' => '2026-04-03',
        ]);
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertResponseIsSuccessful();
        self::assertSame((string) $task->getId(), $data['id']);
        self::assertSame('Updated title', $attributes['title']);
        self::assertSame('completed', $attributes['status']);
        self::assertSame('2026-04-03', $attributes['due_date']);
    }

    #[Test]
    public function updateWhenStatusChangesShouldCreateStatusHistoryRow(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user, 'status' => TaskStatus::TODO]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => TaskStatus::COMPLETED->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
        ]);

        self::assertResponseIsSuccessful();

        $statusChanges = $this->statusChangesForTask($task);

        self::assertCount(1, $statusChanges);
        self::assertSame(TaskStatus::TODO, $statusChanges[0]->getFromStatus());
        self::assertSame(TaskStatus::COMPLETED, $statusChanges[0]->getToStatus());
    }

    #[Test]
    public function updateWhenStatusDoesNotChangeShouldNotCreateStatusHistoryRow(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user, 'status' => TaskStatus::TODO]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Renamed task',
            'description' => $task->getDescription(),
            'status' => TaskStatus::TODO->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
        ]);

        self::assertResponseIsSuccessful();

        $statusChanges = $this->statusChangesForTask($task);

        self::assertCount(0, $statusChanges);
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
    public function updateWhenCancelledShouldPersistCancellationReason(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Updated title',
            'status' => 'cancelled',
            'cancellation_reason' => 'Work is no longer required',
        ]);

        self::assertSame([
            'status' => 'cancelled',
            'cancellation_reason' => 'Work is no longer required',
        ], array_intersect_key($this->jsonAttributes($response), array_flip(['status', 'cancellation_reason'])));
    }

    #[Test]
    public function updateWhenMovingAwayFromCancelledShouldClearCancellationReason(): void
    {
        $task = TaskFactory::new()->cancelled('Outdated')->create(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => 'Updated title',
            'status' => 'completed',
        ]);

        self::assertSame('completed', $this->jsonAttributes($response)['status']);
        self::assertNull($this->jsonAttributes($response)['cancellation_reason']);
    }

    #[Test]
    public function updateWhenMovingAwayFromCancelledShouldCreateStatusHistoryRow(): void
    {
        $task = TaskFactory::new()->cancelled('Outdated')->create(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => TaskStatus::TODO->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
        ]);

        self::assertResponseIsSuccessful();

        $statusChanges = $this->statusChangesForTask($task);

        self::assertCount(1, $statusChanges);
        self::assertSame(TaskStatus::CANCELLED, $statusChanges[0]->getFromStatus());
        self::assertSame(TaskStatus::TODO, $statusChanges[0]->getToStatus());
    }

    #[Test]
    public function updateWhenOnlyCancellationReasonChangesShouldNotCreateStatusHistoryRow(): void
    {
        $task = TaskFactory::new()->cancelled('Outdated')->create(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), [
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => TaskStatus::CANCELLED->value,
            'cancellation_reason' => 'No longer relevant',
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
        ]);

        self::assertResponseIsSuccessful();

        $statusChanges = $this->statusChangesForTask($task);

        self::assertCount(0, $statusChanges);
    }

    #[Test]
    public function getAllWhenDueFromIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_from]=tomorrow');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value is not a valid date. Use the YYYY-MM-DD format.', $json['errors'][0]['message']);
    }

    #[Test]
    public function getAllWhenDueRangeIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_from]=2026-04-05&filter[due_to]=2026-04-01');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value should be greater than or equal to due_from.', $json['errors'][0]['message']);
    }

    #[Test]
    public function getAllWhenDueToIsInvalidShouldReturn422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[due_to]=tomorrow');
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This value is not a valid date. Use the YYYY-MM-DD format.', $json['errors'][0]['message']);
    }

    #[Test]
    public function updateWhenTaskNotFoundShouldReturn404(): void
    {
        $this->put($this->route('api_task_update', ['id' => $this->missingTaskId()]), ['title' => 'Test']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function updateWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->put($this->route('api_task_update', ['id' => $this->taskId($task)]), ['title' => 'Hacked']);

        self::assertResponseStatusCodeSame(403);
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
    public function deleteWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->delete($this->route('api_task_delete', ['id' => $this->taskId($task)]));

        self::assertResponseStatusCodeSame(403);
    }
}
