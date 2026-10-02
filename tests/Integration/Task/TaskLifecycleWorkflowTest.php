<?php

declare(strict_types=1);

namespace App\Tests\Integration\Task;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final class TaskLifecycleWorkflowTest extends KernelTestCase
{
    private const array ALLOWED = [
        ['todo', 'in_progress'],
        ['todo', 'cancelled'],
        ['in_progress', 'in_review'],
        ['in_progress', 'cancelled'],
        ['in_review', 'completed'],
        ['in_review', 'cancelled'],
    ];

    #[Test]
    public function workflowShouldAllowOnlyTheLifecycle(): void
    {
        $workflow = static::getContainer()->get('state_machine.task_lifecycle');
        \assert($workflow instanceof WorkflowInterface);

        $allowed = [];

        foreach (TaskStatus::cases() as $from) {
            $task = new Task(Uuid::v7());
            $task->setStatus($from);

            foreach ($workflow->getEnabledTransitions($task) as $transition) {
                foreach ($transition->getTos() as $to) {
                    $allowed[] = [$from->value, $to];
                }
            }
        }

        self::assertEqualsCanonicalizing(self::ALLOWED, $allowed);
    }

    #[Test]
    public function taskItselfAcceptsAnyStatusOutsideTheWorkflow(): void
    {
        $task = new Task(Uuid::v7());

        $task->setStatus(TaskStatus::COMPLETED);
        self::assertSame(TaskStatus::COMPLETED, $task->getStatus());

        $task->setStatus(TaskStatus::CANCELLED);
        self::assertSame(TaskStatus::CANCELLED, $task->getStatus());
        self::assertNull($task->getCancellationReason());
    }
}
