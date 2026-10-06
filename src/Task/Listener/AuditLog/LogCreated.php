<?php

declare(strict_types=1);

namespace App\Task\Listener\AuditLog;

use App\Task\AuditLog\TaskAuditLog;
use App\Task\Event\TaskCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogCreated
{
    public function __construct(
        private TaskAuditLog $taskAuditLog,
    ) {
    }

    public function __invoke(TaskCreated $event): void
    {
        $this->taskAuditLog->created($event->taskId, $event->actorId, $event->state);
    }
}
