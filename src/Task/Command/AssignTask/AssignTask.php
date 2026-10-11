<?php

declare(strict_types=1);

namespace App\Task\Command\AssignTask;

use Symfony\Component\Uid\Uuid;

final readonly class AssignTask
{
    public function __construct(
        public Uuid $taskId,
        public int $assigneeId,
        public int $actorId,
    ) {
    }
}
