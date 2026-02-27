<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $services = $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('App\Task\\', __DIR__)
        ->exclude([
            __DIR__.'/DTO/',
            __DIR__.'/Entity/',
            __DIR__.'/Enum/',
            __DIR__.'/{di,routing}.php',
        ]);
};
