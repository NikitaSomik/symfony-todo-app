<?php

declare(strict_types=1);

namespace App\Task\Contract;

use Symfony\Component\Uid\Uuid;

/**
 * A task got a new assignee. Announced after the change has committed, so it is never about
 * an assignment that was rolled back.
 */
final readonly class TaskAssigned
{
    public function __construct(
        public Uuid $taskId,
        public Uuid $workspaceId,
        public int $assigneeId,
        public int $actorId,
    ) {
    }
}
