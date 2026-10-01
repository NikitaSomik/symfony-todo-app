<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Entity;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Exception\TaskTransitionNotAllowedException;
use App\Task\ValueObject\CancellationReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TaskTest extends TestCase
{
    #[Test]
    public function newTaskShouldStartInTodo(): void
    {
        self::assertSame(TaskStatus::TODO, new Task(Uuid::v7())->getStatus());
    }

    #[Test]
    public function taskShouldMoveThroughItsLifecycleToCompleted(): void
    {
        $task = new Task(Uuid::v7());

        $task->start();
        self::assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());

        $task->submitForReview();
        self::assertSame(TaskStatus::IN_REVIEW, $task->getStatus());

        $task->complete();
        self::assertSame(TaskStatus::COMPLETED, $task->getStatus());
        self::assertNull($task->getCancellationReason());
    }

    #[Test]
    public function cancelShouldKeepTheReason(): void
    {
        $task = new Task(Uuid::v7());

        $task->cancel(new CancellationReason('No longer needed'));

        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertSame('No longer needed', $task->getCancellationReason());
    }

    #[Test]
    public function completeWhenTaskIsNotInReviewShouldBeRefused(): void
    {
        $task = new Task(Uuid::v7());

        $this->expectException(TaskTransitionNotAllowedException::class);
        $this->expectExceptionMessage('A task in status "todo" cannot move to "completed".');

        $task->complete();
    }

    #[Test]
    public function refusedTransitionShouldLeaveTheTaskUnchanged(): void
    {
        $task = new Task(Uuid::v7());
        $task->cancel(new CancellationReason('No longer needed'));

        try {
            $task->cancel(new CancellationReason('Another reason'));
            self::fail('A cancelled task is final.');
        } catch (TaskTransitionNotAllowedException) {
        }

        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertSame('No longer needed', $task->getCancellationReason());
    }
}
