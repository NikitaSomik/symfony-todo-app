<?php

declare(strict_types=1);

namespace App\Tests\Application\Task;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Shared\AuditLog\Entity\AuditLog;
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

final class TaskTransitionTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    private function taskIn(TaskStatus $status, ?User $user = null): Task
    {
        return TaskFactory::createOne([
            'user' => $user ?? $this->user,
            'title' => 'Buy milk',
            'status' => $status,
            'cancellationReason' => TaskStatus::CANCELLED === $status ? 'Outdated' : null,
            'blockReason' => TaskStatus::BLOCKED === $status ? 'Waiting for access' : null,
        ]);
    }

    /** @param array<string, mixed> $body */
    private function transition(string $transition, Task $task, array $body = []): Response
    {
        return $this->post($this->route('api_task_'.$transition, ['id' => $task->getId()->toRfc4122()]), $body);
    }

    /** @return list<array{string, string}> */
    private function statusHistory(Task $task): array
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get(ManagerRegistry::class);

        return array_map(
            static fn (TaskStatusChange $change): array => [$change->getFromStatus()->value, $change->getToStatus()->value],
            $registry->getRepository(TaskStatusChange::class)->findBy(['task' => $task], ['changedAt' => 'ASC', 'id' => 'ASC']),
        );
    }

    /** @return AuditLog[] */
    private function auditLogs(Task $task): array
    {
        return static::getContainer()->get(AuditLogRepository::class)->findForEntity(AuditLogEntityType::TASK, $task->getId()->toRfc4122());
    }

    #[Test]
    #[TestWith(['start', TaskStatus::TODO, 'in_progress'])]
    #[TestWith(['submit_for_review', TaskStatus::IN_PROGRESS, 'in_review'])]
    #[TestWith(['complete', TaskStatus::IN_REVIEW, 'completed'])]
    public function transitionShouldMoveTheTaskAndRecordIt(string $transition, TaskStatus $from, string $expectedStatus): void
    {
        $task = $this->taskIn($from);

        $response = $this->transition($transition, $task);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedStatus, $this->jsonAttributes($response)['status']);
        self::assertSame([[$from->value, $expectedStatus]], $this->statusHistory($task));

        $auditLogs = $this->auditLogs($task);

        self::assertCount(1, $auditLogs);
        self::assertSame('Updated task status for "Buy milk"', $auditLogs[0]->getMessage());
        self::assertSame(['old' => ['status' => $from->value], 'new' => ['status' => $expectedStatus]], $auditLogs[0]->getAttributeChanges());
        self::assertSame($this->user->getId(), $auditLogs[0]->getUser()?->getId());
    }

    #[Test]
    #[TestWith([TaskStatus::TODO])]
    #[TestWith([TaskStatus::IN_PROGRESS])]
    #[TestWith([TaskStatus::BLOCKED])]
    #[TestWith([TaskStatus::IN_REVIEW])]
    public function cancelShouldBePossibleFromEveryStatusThatIsNotFinal(TaskStatus $from): void
    {
        $task = $this->taskIn($from);

        $response = $this->transition('cancel', $task, ['reason' => 'No longer needed']);

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->jsonAttributes($response)['status']);
        self::assertSame('No longer needed', $this->jsonAttributes($response)['cancellation_reason']);
        self::assertSame([[$from->value, 'cancelled']], $this->statusHistory($task));
    }

    #[Test]
    public function blockShouldKeepTheReasonAndLogIt(): void
    {
        $task = $this->taskIn(TaskStatus::IN_PROGRESS);

        $response = $this->transition('block', $task, ['reason' => '  Waiting for access  ']);

        self::assertResponseIsSuccessful();
        self::assertSame('blocked', $this->jsonAttributes($response)['status']);
        self::assertSame('Waiting for access', $this->jsonAttributes($response)['block_reason']);
        self::assertSame([['in_progress', 'blocked']], $this->statusHistory($task));
        self::assertSame(
            ['Updated task status for "Buy milk"', 'Updated task block reason for "Buy milk"'],
            array_map(static fn (AuditLog $log): string => $log->getMessage(), $this->auditLogs($task)),
        );
    }

    #[Test]
    public function unblockShouldReturnToWorkAndClearTheReason(): void
    {
        $task = $this->taskIn(TaskStatus::BLOCKED);

        $response = $this->transition('unblock', $task);

        self::assertResponseIsSuccessful();
        self::assertSame('in_progress', $this->jsonAttributes($response)['status']);
        self::assertNull($this->jsonAttributes($response)['block_reason']);
        self::assertSame([['blocked', 'in_progress']], $this->statusHistory($task));
        self::assertSame(
            ['Updated task status for "Buy milk"', 'Updated task block reason for "Buy milk"'],
            array_map(static fn (AuditLog $log): string => $log->getMessage(), $this->auditLogs($task)),
        );
    }

    #[Test]
    #[TestWith([TaskStatus::TODO])]
    #[TestWith([TaskStatus::IN_REVIEW])]
    #[TestWith([TaskStatus::BLOCKED])]
    public function blockOutsideWorkInProgressShouldReturn409(TaskStatus $from): void
    {
        $this->transition('block', $this->taskIn($from), ['reason' => 'Waiting for access']);

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    #[TestWith([['reason' => '']])]
    #[TestWith([['reason' => '   ab   ']])]
    public function blockWithoutAUsableReasonShouldReturn422(array $body): void
    {
        $task = $this->taskIn(TaskStatus::IN_PROGRESS);

        $this->transition('block', $task, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->statusHistory($task));
    }

    #[Test]
    public function cancelShouldTrimTheReason(): void
    {
        $response = $this->transition('cancel', $this->taskIn(TaskStatus::TODO), ['reason' => '  No longer needed  ']);

        self::assertSame('No longer needed', $this->jsonAttributes($response)['cancellation_reason']);
    }

    #[Test]
    public function cancelShouldLogTheStatusAndTheReason(): void
    {
        $task = $this->taskIn(TaskStatus::TODO);

        $this->transition('cancel', $task, ['reason' => 'No longer needed']);

        self::assertSame(
            ['Updated task status for "Buy milk"', 'Updated task cancellation reason for "Buy milk"'],
            array_map(static fn (AuditLog $log): string => $log->getMessage(), $this->auditLogs($task)),
        );
    }

    #[Test]
    #[TestWith(['complete', TaskStatus::TODO, 'A task in status "todo" cannot move to "completed".'])]
    #[TestWith(['start', TaskStatus::IN_PROGRESS, 'A task in status "in_progress" cannot move to "in_progress".'])]
    #[TestWith(['start', TaskStatus::COMPLETED, 'A task in status "completed" cannot move to "in_progress".'])]
    #[TestWith(['submit_for_review', TaskStatus::CANCELLED, 'A task in status "cancelled" cannot move to "in_review".'])]
    #[TestWith(['complete', TaskStatus::BLOCKED, 'A task in status "blocked" cannot move to "completed".'])]
    #[TestWith(['submit_for_review', TaskStatus::BLOCKED, 'A task in status "blocked" cannot move to "in_review".'])]
    #[TestWith(['unblock', TaskStatus::IN_PROGRESS, 'A task in status "in_progress" cannot move to "in_progress".'])]
    public function transitionOutsideTheLifecycleShouldReturn409AndChangeNothing(string $transition, TaskStatus $from, string $detail): void
    {
        $task = $this->taskIn($from);

        $response = $this->transition($transition, $task);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('409', $this->json($response)['errors'][0]['status']);
        self::assertSame($detail, $this->json($response)['errors'][0]['detail']);
        self::assertSame($from, static::getContainer()->get(TaskRepository::class)->find($task->getId())->getStatus());
        self::assertSame([], $this->statusHistory($task));
        self::assertSame([], $this->auditLogs($task));
    }

    #[Test]
    #[TestWith([TaskStatus::COMPLETED])]
    #[TestWith([TaskStatus::CANCELLED])]
    public function cancelOfAFinalTaskShouldReturn409(TaskStatus $from): void
    {
        $task = $this->taskIn($from);

        $this->transition('cancel', $task, ['reason' => 'No longer needed']);

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    #[TestWith([['reason' => null]])]
    #[TestWith([['reason' => '']])]
    #[TestWith([['reason' => 'ab']])]
    #[TestWith([['reason' => '   ab   ']])]
    public function cancelWithoutAUsableReasonShouldReturn422(array $body): void
    {
        $task = $this->taskIn(TaskStatus::TODO);

        $this->transition('cancel', $task, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->statusHistory($task));
    }

    #[Test]
    public function cancelWithAnEmptyObjectShouldReturn422(): void
    {
        $task = $this->taskIn(TaskStatus::TODO);

        $response = $this->sendRaw('POST', $this->route('api_task_cancel', ['id' => $task->getId()->toRfc4122()]), 'application/json', '{}');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('/reason', $this->json($response)['errors'][0]['source']['pointer']);
    }

    #[Test]
    #[TestWith(['start'])]
    #[TestWith(['submit_for_review'])]
    #[TestWith(['complete'])]
    #[TestWith(['cancel'])]
    #[TestWith(['block'])]
    #[TestWith(['unblock'])]
    public function transitionOfSomeoneElsesTaskShouldReturn404(string $transition): void
    {
        $task = $this->taskIn(TaskStatus::TODO, UserFactory::createOne());

        // No body: for cancel it is invalid, and a 422 here would give the task away.
        $this->transition($transition, $task);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function transitionShouldRollBackWhenTheAuditLogFails(): void
    {
        $task = $this->taskIn(TaskStatus::TODO);
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->transition('start', $task);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(TaskStatus::TODO, static::getContainer()->get(TaskRepository::class)->find($task->getId())->getStatus());
        self::assertSame([], $this->statusHistory($task));
    }
}
