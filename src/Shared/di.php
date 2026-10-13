<?php

declare(strict_types=1);

namespace App\Shared;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\Shared\\', __DIR__)
            ->exclude([__DIR__.'/{di,routing}.php'])
        ->set(Messaging\EventPublisher::class)
            ->arg('$eventBus', service('event.bus'));
};
