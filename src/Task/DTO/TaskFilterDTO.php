<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TaskFilterDTO
{
    public function __construct(
        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public ?string $status = null,
    ) {
    }
}
