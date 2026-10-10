<?php

declare(strict_types=1);

use App\Task\Contract\TaskAssigned;
use App\Tests\Support\AuditLogFailureToggle;
use App\Tests\Support\FailAuditLogEventSubscriber;
use App\Tests\Support\PublishedEvents;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->set(AuditLogFailureToggle::class)
        ->set(FailAuditLogEventSubscriber::class)
        ->args([service(AuditLogFailureToggle::class)])
        ->tag('kernel.event_subscriber')
        ->set(PublishedEvents::class)
        ->tag('messenger.message_handler', ['bus' => 'event.bus', 'handles' => TaskAssigned::class]);
};
