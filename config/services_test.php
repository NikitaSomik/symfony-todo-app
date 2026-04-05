<?php

declare(strict_types=1);

use App\Tests\Support\ActivityFailureToggle;
use App\Tests\Support\FailActivityEventSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->set(ActivityFailureToggle::class)
        ->set(FailActivityEventSubscriber::class)
        ->args([service(ActivityFailureToggle::class)])
        ->tag('kernel.event_subscriber');
};
