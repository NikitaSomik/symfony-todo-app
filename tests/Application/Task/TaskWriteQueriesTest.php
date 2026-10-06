<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Entity\Task;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Each write must reach the tasks table once. A column that PostgreSQL computes must not be read back
 * after the write: Doctrine would not update its snapshot, the task would look changed, and the next
 * flush would send an extra UPDATE of updated_at (doctrine/orm#12017).
 */
final class TaskWriteQueriesTest extends ApiTestCase
{
    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
        $this->workspace = WorkspaceFactory::createOne(['owner' => $this->user]);
    }

    /** @return list<string> */
    private function sqlOnTasks(): array
    {
        return array_values(array_filter(
            $this->executedSql(),
            static fn (string $sql): bool => 1 === preg_match('/^(INSERT INTO|UPDATE|DELETE FROM) tasks\b|^SELECT search_vector\b/', $sql),
        ));
    }

    private function assertNoPendingTaskUpdates(): void
    {
        $unitOfWork = static::getContainer()->get(EntityManagerInterface::class)->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        self::assertSame([], array_filter($unitOfWork->getScheduledEntityUpdates(), static fn (object $entity): bool => $entity instanceof Task));
    }

    #[Test]
    public function createShouldInsertTaskOnce(): void
    {
        $this->withProfiler()->post($this->route('api_workspace_task_create', ['id' => $this->workspace->getId()->toRfc4122()]), ['title' => 'Buy running shoes']);

        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $this->sqlOnTasks());
        self::assertStringStartsWith('INSERT INTO tasks', $this->sqlOnTasks()[0]);
        $this->assertNoPendingTaskUpdates();
    }

    #[Test]
    public function updateOfSearchedTextShouldUpdateTaskOnce(): void
    {
        $task = TaskFactory::createOne(['workspace' => $this->workspace, 'title' => 'Buy running shoes', 'description' => null]);

        $this->withProfiler()->put($this->route('api_task_update', ['id' => $task->getId()->toRfc4122()]), [
            'title' => 'Buy walking boots',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['UPDATE tasks SET title = ?, updated_at = ? WHERE id = ?'], $this->sqlOnTasks());
        $this->assertNoPendingTaskUpdates();
    }

    #[Test]
    public function transitionShouldUpdateTaskOnce(): void
    {
        $task = TaskFactory::createOne(['workspace' => $this->workspace]);

        $this->withProfiler()->post($this->route('api_task_start', ['id' => $task->getId()->toRfc4122()]));

        self::assertResponseIsSuccessful();
        self::assertSame(['UPDATE tasks SET status = ?, updated_at = ? WHERE id = ?'], $this->sqlOnTasks());
        // The task adds the new row to its history without loading the rows that are already there.
        self::assertSame(
            ['INSERT INTO task_status_changes'],
            array_values(array_unique(preg_filter('/^(\w+(?: INTO)?) .*\btask_status_changes\b.*$/s', '$1 task_status_changes', $this->executedSql()))),
        );
        $this->assertNoPendingTaskUpdates();
    }
}
