<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Entity;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TaskTest extends TestCase
{
    #[Test]
    public function setCancellationReasonWhenTaskIsNotCancelledShouldThrowLogicException(): void
    {
        $task = new Task(Uuid::v7());
        $task->changeStatus(TaskStatus::TODO);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cancellation reason can only be set when task is cancelled.');

        $task->setCancellationReason('No longer needed');
    }
}
