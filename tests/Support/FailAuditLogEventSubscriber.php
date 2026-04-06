<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Task\Event\TaskCreated;
use App\Task\Event\TaskDeleted;
use App\Task\Event\TaskUpdated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FailAuditLogEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogFailureToggle $toggle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskCreated::class => 'onAuditLogEvent',
            TaskUpdated::class => 'onAuditLogEvent',
            TaskDeleted::class => 'onAuditLogEvent',
        ];
    }

    public function onAuditLogEvent(object $event): void
    {
        if (!$this->toggle->enabled()) {
            return;
        }

        throw new \RuntimeException('Simulated audit log event failure.');
    }
}
