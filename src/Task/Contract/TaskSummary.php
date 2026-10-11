<?php

declare(strict_types=1);

namespace App\Task\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class TaskSummary
{
    public function __construct(
        public Uuid $id,
        public string $title,
        public ?int $assigneeId,
    ) {
    }
}
