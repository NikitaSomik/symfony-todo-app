<?php

declare(strict_types=1);

namespace App\Story;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $services = $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('App\Story\\', __DIR__)
        ->exclude([__DIR__.'/{di,routing}.php']);
};
