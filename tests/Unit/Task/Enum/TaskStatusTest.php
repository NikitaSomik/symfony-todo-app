<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Enum;

use App\Task\Enum\TaskStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaskStatusTest extends TestCase
{
    private const array ALLOWED = [
        ['todo', 'in_progress'],
        ['todo', 'cancelled'],
        ['in_progress', 'in_review'],
        ['in_progress', 'cancelled'],
        ['in_progress', 'blocked'],
        ['blocked', 'in_progress'],
        ['blocked', 'cancelled'],
        ['in_review', 'completed'],
        ['in_review', 'cancelled'],
    ];

    #[Test]
    public function canTransitionToShouldAllowOnlyTheLifecycle(): void
    {
        $allowed = [];

        foreach (TaskStatus::cases() as $from) {
            foreach (TaskStatus::cases() as $to) {
                if ($from->canTransitionTo($to)) {
                    $allowed[] = [$from->value, $to->value];
                }
            }
        }

        self::assertEqualsCanonicalizing(self::ALLOWED, $allowed);
    }
}
