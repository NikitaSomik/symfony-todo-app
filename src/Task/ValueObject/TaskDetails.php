<?php

declare(strict_types=1);

namespace App\Task\ValueObject;

final readonly class TaskDetails
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?\DateTimeImmutable $dueDate,
    ) {
    }
}
