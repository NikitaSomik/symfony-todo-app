<?php

declare(strict_types=1);

namespace App\Task\Contract;

use Symfony\Component\Uid\Uuid;

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
