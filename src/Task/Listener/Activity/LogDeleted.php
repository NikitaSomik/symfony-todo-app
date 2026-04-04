<?php

declare(strict_types=1);

namespace App\Task\Listener\Activity;

use App\Task\Activity\TaskActivity;
use App\Task\Event\TaskDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogDeleted
{
    public function __construct(
        private TaskActivity $taskActivity,
    ) {
    }

    public function __invoke(TaskDeleted $event): void
    {
        $this->taskActivity->deleted($event->taskId, $event->actor, $event->state);
    }
}
