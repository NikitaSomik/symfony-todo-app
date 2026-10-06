<?php

declare(strict_types=1);

namespace App\Task\Listener\AuditLog;

use App\Task\AuditLog\TaskAuditLog;
use App\Task\Event\TaskUpdated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogUpdated
{
    public function __construct(
        private TaskAuditLog $taskAuditLog,
    ) {
    }

    public function __invoke(TaskUpdated $event): void
    {
        $this->taskAuditLog->updated($event->taskId, $event->actorId, $event->previousState, $event->currentState);
    }
}
