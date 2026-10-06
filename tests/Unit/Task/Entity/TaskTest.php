<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Entity;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Exception\TaskTransitionNotAllowedException;
use App\Task\ValueObject\BlockReason;
use App\Task\ValueObject\CancellationReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TaskTest extends TestCase
{
    #[Test]
    public function newTaskShouldStartInTodo(): void
    {
        self::assertSame(TaskStatus::TODO, new Task(Uuid::v7(), 1)->getStatus());
    }

    #[Test]
    public function taskShouldMoveThroughItsLifecycleToCompleted(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');

        $task->start($at);
        self::assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());

        $task->submitForReview($at);
        self::assertSame(TaskStatus::IN_REVIEW, $task->getStatus());

        $task->complete($at);
        self::assertSame(TaskStatus::COMPLETED, $task->getStatus());
        self::assertNull($task->getCancellationReason());
    }

    #[Test]
    public function cancelShouldKeepTheReason(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');

        $task->cancel(new CancellationReason('No longer needed'), $at);

        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertSame('No longer needed', $task->getCancellationReason());
    }

    #[Test]
    public function completeWhenTaskIsNotInReviewShouldBeRefused(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');

        $this->expectException(TaskTransitionNotAllowedException::class);
        $this->expectExceptionMessage('A task in status "todo" cannot move to "completed".');

        $task->complete($at);
    }

    #[Test]
    public function refusedTransitionShouldLeaveTheTaskUnchanged(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');
        $task->cancel(new CancellationReason('No longer needed'), $at);

        try {
            $task->cancel(new CancellationReason('Another reason'), $at);
            self::fail('A cancelled task is final.');
        } catch (TaskTransitionNotAllowedException) {
        }

        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertSame('No longer needed', $task->getCancellationReason());
    }

    #[Test]
    public function blockShouldKeepTheReasonUntilTheTaskIsUnblocked(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');
        $task->start($at);

        $task->block(new BlockReason('Waiting for access'), $at);
        self::assertSame(TaskStatus::BLOCKED, $task->getStatus());
        self::assertSame('Waiting for access', $task->getBlockReason());

        $task->unblock($at);
        self::assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        self::assertNull($task->getBlockReason());
    }

    #[Test]
    public function cancellingABlockedTaskShouldDropTheBlockReason(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');
        $task->start($at);
        $task->block(new BlockReason('Waiting for access'), $at);

        $task->cancel(new CancellationReason('Client withdrew the request'), $at);

        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertNull($task->getBlockReason());
        self::assertSame('Client withdrew the request', $task->getCancellationReason());
    }

    #[Test]
    public function taskThatWasNotStartedShouldNotBeBlocked(): void
    {
        $task = new Task(Uuid::v7(), 1);

        $this->expectException(TaskTransitionNotAllowedException::class);
        $this->expectExceptionMessage('A task in status "todo" cannot move to "blocked".');

        $task->block(new BlockReason('Waiting for access'), new \DateTimeImmutable());
    }

    #[Test]
    public function unblockShouldNotStartATaskThatWasNeverBlocked(): void
    {
        $task = new Task(Uuid::v7(), 1);

        $this->expectException(TaskTransitionNotAllowedException::class);

        $task->unblock(new \DateTimeImmutable());
    }

    #[Test]
    public function startShouldNotUnblockABlockedTask(): void
    {
        $task = new Task(Uuid::v7(), 1);
        $at = new \DateTimeImmutable('2026-04-01 10:00:00');
        $task->start($at);
        $task->block(new BlockReason('Waiting for access'), $at);

        $this->expectException(TaskTransitionNotAllowedException::class);

        $task->start($at);
    }
}
