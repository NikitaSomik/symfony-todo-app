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

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json($response));
    }

    #[Test]
    public function getAllWhenTasksExistShouldReturnAll(): void
    {
        TaskFactory::createMany(3, ['user' => $this->user]);

        $response = $this->get($this->route('api_task_get_all'));

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->json($response));
    }

    #[Test]
    public function getAllShouldNotReturnOtherUsersTask(): void
    {
        $otherUser = UserFactory::createOne();
        TaskFactory::createOne(['user' => $otherUser]);
        TaskFactory::createMany(2, ['user' => $this->user]);

        $response = $this->get($this->route('api_task_get_all'));

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->json($response));
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
        $data = $this->json($response);

        self::assertSame('Buy milk', $data['title']);
        self::assertSame(TaskStatus::TODO->value, $data['status']);
        self::assertNull($data['description']);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('created_at', $data);
        self::assertArrayHasKey('updated_at', $data);
    }

    #[Test]
    public function createWhenAllFieldsProvidedShouldReturnTask(): void
    {
        $response = $this->post($this->route('api_task_create'), [
            'title' => 'Buy milk',
            'description' => '2 liters',
            'status' => 'in_progress',
        ]);

        $this->assertJsonContains([
            'title' => 'Buy milk',
            'description' => '2 liters',
            'status' => 'in_progress',
        ], $response);
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
    public function getWhenTaskExistsShouldReturnTask(): void
    {
        $task = TaskFactory::createOne(['title' => 'Buy milk', 'user' => $this->user]);

        $response = $this->get($this->route('api_task_get', ['id' => $task->getId()]));

        self::assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $task->getId(),
            'title' => 'Buy milk',
        ], $response);

        $data = $this->json($response);
        self::assertArrayHasKey('description', $data);
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('created_at', $data);
        self::assertArrayHasKey('updated_at', $data);
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

        self::assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $task->getId(),
            'title' => 'Updated title',
            'status' => 'completed',
        ], $response);
    }

    #[Test]
    public function updateWhenTitleIsEmptyShouldReturn422(): void
    {
        $task = TaskFactory::createOne(['user' => $this->user]);

        $this->put($this->route('api_task_update', ['id' => $task->getId()]), ['title' => '']);

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
