<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\DataFixtures\UserFactory;
use App\Auth\Entity\User;
use App\Task\DataFixtures\TaskFactory;
use App\Task\Enum\TaskStatus;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TaskControllerTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
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
    public function getAllShouldSortByTitleAscending(): void
    {
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Zulu']);
        TaskFactory::createOne(['user' => $this->user, 'title' => 'Alpha']);

        $response = $this->get('/api/v1/tasks?sort=title&direction=asc');
        $data = $this->jsonData($response);

        self::assertResponseIsSuccessful();
        self::assertSame('Alpha', $data[0]['attributes']['title']);
        self::assertSame('Zulu', $data[1]['attributes']['title']);
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
        ]);

        self::assertSame([
            'title' => 'Buy milk',
            'description' => '2 liters',
            'status' => 'in_progress',
        ], array_intersect_key($this->jsonAttributes($response), array_flip(['title', 'description', 'status'])));
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
    public function getWhenTaskExistsShouldReturnTask(): void
    {
        $task = TaskFactory::createOne(['title' => 'Buy milk', 'user' => $this->user]);

        $response = $this->get($this->route('api_task_get', ['id' => $task->getId()]));
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertResponseIsSuccessful();
        self::assertSame((string) $task->getId(), $data['id']);
        self::assertSame('Buy milk', $attributes['title']);
        self::assertArrayHasKey('description', $attributes);
        self::assertArrayHasKey('status', $attributes);
        self::assertArrayHasKey('created_at', $attributes);
        self::assertArrayHasKey('updated_at', $attributes);
    }

    #[Test]
    public function getWhenTaskNotFoundShouldReturn404(): void
    {
        $this->get($this->route('api_task_get', ['id' => 99999]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function getWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->get($this->route('api_task_get', ['id' => $task->getId()]));

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function updateWhenValidDataShouldReturnUpdatedTask(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $response = $this->put($this->route('api_task_update', ['id' => $task->getId()]), [
            'title' => 'Updated title',
            'status' => 'completed',
        ]);
        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertResponseIsSuccessful();
        self::assertSame((string) $task->getId(), $data['id']);
        self::assertSame('Updated title', $attributes['title']);
        self::assertSame('completed', $attributes['status']);
    }

    #[Test]
    public function updateWhenTitleIsEmptyShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $task->getId()]), ['title' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenDescriptionIsTooShortShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $task->getId()]), ['title' => 'Test', 'description' => 'ab']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenDescriptionIsTooLongShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $task->getId()]), [
            'title' => 'Test',
            'description' => str_repeat('a', 2001),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function updateWhenTaskNotFoundShouldReturn404(): void
    {
        $this->put($this->route('api_task_update', ['id' => 99999]), ['title' => 'Test']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function updateWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->put($this->route('api_task_update', ['id' => $task->getId()]), ['title' => 'Hacked']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function deleteWhenTaskExistsShouldReturn204(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->delete($this->route('api_task_delete', ['id' => $task->getId()]));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function deleteWhenTaskDeletedShouldReturn404OnGet(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);
        $id = $task->getId();

        $this->delete($this->route('api_task_delete', ['id' => $id]));
        $this->get($this->route('api_task_get', ['id' => $id]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteWhenTaskNotFoundShouldReturn404(): void
    {
        $this->delete($this->route('api_task_delete', ['id' => 99999]));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteWhenTaskBelongsToAnotherUserShouldReturn403(): void
    {
        $otherUser = UserFactory::createOne();
        $task = TaskFactory::createOne(['user' => $otherUser]);

        $this->delete($this->route('api_task_delete', ['id' => $task->getId()]));

        self::assertResponseStatusCodeSame(403);
    }
}
