<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\Notification\\', __DIR__)
            ->exclude([__DIR__.'/{di,routing}.php']);
};
