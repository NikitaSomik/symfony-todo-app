<?php

declare(strict_types=1);

namespace App\Task\Listener\AuditLog;

use App\Task\AuditLog\TaskAuditLog;
use App\Task\Event\TaskDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogDeleted
{
    public function __construct(
        private TaskAuditLog $taskAuditLog,
    ) {
    }

    public function __invoke(TaskDeleted $event): void
    {
        $this->taskAuditLog->deleted($event->taskId, $event->actorId, $event->state);
    }
}
