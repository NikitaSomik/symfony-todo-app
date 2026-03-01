<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $services = $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('App\Auth\\', __DIR__)
        ->exclude([
            __DIR__.'/DTO/',
            __DIR__.'/Entity/',
            __DIR__.'/{di,routing}.php',
        ]);
};
