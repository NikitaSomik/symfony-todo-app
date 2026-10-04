<?php

declare(strict_types=1);

namespace App\AuditLog;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\AuditLog\\', __DIR__)
            ->exclude([
                __DIR__.'/Entity/',
                __DIR__.'/Enum/',
                __DIR__.'/di.php',
            ]);
};
