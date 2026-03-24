<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di, string $env): void {
    $di->parameters()
        ->set('app.jwt_ttl', 900)
        ->set('app.cookie_secure', $env === 'prod');

    $di->import('../src/**/di.php');
};
