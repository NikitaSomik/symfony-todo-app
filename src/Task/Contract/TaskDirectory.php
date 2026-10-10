<?php

declare(strict_types=1);

namespace App\Task\Contract;

use Symfony\Component\Uid\Uuid;

interface TaskDirectory
{
    public function findSummary(Uuid $taskId): ?TaskSummary;
}
