<?php

declare(strict_types=1);

namespace App\Task\Listener\AuditLog;

use App\Task\AuditLog\TaskAuditLog;
use App\Task\Event\TaskStatusChanged;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogStatusChanged
{
    public function __construct(
        private TaskAuditLog $taskAuditLog,
    ) {
    }

    public function __invoke(TaskStatusChanged $event): void
    {
        $this->taskAuditLog->updated($event->taskId, $event->actor, $event->previousState, $event->currentState);
    }
}
