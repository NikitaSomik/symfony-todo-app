<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di, string $env): void {
    $di->parameters()
        ->set('app.jwt_ttl', 900)
        ->set('app.refresh_token_ttl', 30) // days
        ->set('app.cookie_secure', !in_array($env, ['dev', 'test'], true));

    $di->import('../src/**/di.php');
};
