<?php

declare(strict_types=1);

namespace App\Workspace;

use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Repository\WorkspaceRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\Workspace\\', __DIR__)
            ->exclude([
                __DIR__.'/Contract/',
                __DIR__.'/DTO/',
                __DIR__.'/Entity/',
                __DIR__.'/Enum/',
                __DIR__.'/{di,routing}.php',
            ]);

    $di->services()->alias(WorkspaceAccess::class, WorkspaceRepository::class);
};
