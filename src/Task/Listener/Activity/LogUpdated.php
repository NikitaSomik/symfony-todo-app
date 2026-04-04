<?php

declare(strict_types=1);

namespace App\Task\Listener\Activity;

use App\Task\Activity\TaskActivity;
use App\Task\Event\TaskUpdated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogUpdated
{
    public function __construct(
        private TaskActivity $taskActivity,
    ) {
    }

    public function __invoke(TaskUpdated $event): void
    {
        $this->taskActivity->updated($event->taskId, $event->actor, $event->previousState, $event->currentState);
    }
}
