<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Task\Event\TaskCreated;
use App\Task\Event\TaskDeleted;
use App\Task\Event\TaskUpdated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FailActivityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityFailureToggle $toggle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskCreated::class => 'onActivityEvent',
            TaskUpdated::class => 'onActivityEvent',
            TaskDeleted::class => 'onActivityEvent',
        ];
    }

    public function onActivityEvent(object $event): void
    {
        if (!$this->toggle->enabled()) {
            return;
        }

        throw new \RuntimeException('Simulated activity event failure.');
    }
}
