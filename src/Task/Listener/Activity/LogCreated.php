<?php

declare(strict_types=1);

namespace App\Task\Listener\Activity;

use App\Task\Activity\TaskActivity;
use App\Task\Event\TaskCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogCreated
{
    public function __construct(
        private TaskActivity $taskActivity,
    ) {
    }

    public function __invoke(TaskCreated $event): void
    {
        $this->taskActivity->created($event->taskId, $event->actor, $event->state);
    }
}
