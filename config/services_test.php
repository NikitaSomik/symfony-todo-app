<?php

declare(strict_types=1);

use App\Tests\Support\AuditLogFailureToggle;
use App\Tests\Support\FailAuditLogEventSubscriber;
use App\Tests\Support\FailFlushToggle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->set(AuditLogFailureToggle::class)
        ->set(FailAuditLogEventSubscriber::class)
        ->args([service(AuditLogFailureToggle::class)])
        ->tag('kernel.event_subscriber')
        ->set(FailFlushToggle::class)
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);
};
