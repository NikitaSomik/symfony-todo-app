<?php

declare(strict_types=1);

namespace App\Task\Contract;

use Symfony\Component\Uid\Uuid;

interface TaskDirectory
{
    /**
     * The task as it is now, or null when it no longer exists.
     */
    public function findSummary(Uuid $taskId): ?TaskSummary;
}
