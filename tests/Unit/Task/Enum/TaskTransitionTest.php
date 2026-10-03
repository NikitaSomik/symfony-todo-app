<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Enum;

use App\Task\Enum\TaskStatus;
use App\Task\Enum\TaskTransition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TaskTransitionTest extends TestCase
{
    #[Test]
    #[TestWith(['todo', ['start', 'cancel']])]
    #[TestWith(['in_progress', ['submit_for_review', 'block', 'cancel']])]
    #[TestWith(['blocked', ['unblock', 'cancel']])]
    #[TestWith(['in_review', ['complete', 'cancel']])]
    #[TestWith(['completed', []])]
    #[TestWith(['cancelled', []])]
    public function eachStatusShouldAllowOnlyItsOwnTransitions(string $status, array $expected): void
    {
        self::assertSame($expected, array_map(
            static fn (TaskTransition $transition): string => $transition->value,
            TaskTransition::availableFrom(TaskStatus::from($status)),
        ));
    }

    #[Test]
    public function transitionsThatLeadToTheSameStatusShouldStayDistinct(): void
    {
        self::assertSame(TaskTransition::START->toStatus(), TaskTransition::UNBLOCK->toStatus());
        self::assertFalse(TaskTransition::START->isAllowedFrom(TaskStatus::BLOCKED));
        self::assertFalse(TaskTransition::UNBLOCK->isAllowedFrom(TaskStatus::TODO));
    }
}
