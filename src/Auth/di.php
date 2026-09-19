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
            __DIR__.'/DataFixtures/',
            __DIR__.'/DTO/',
            __DIR__.'/Entity/',
            __DIR__.'/RefreshToken/',
            __DIR__.'/{di,routing}.php',
        ]);

    $services->set(Factory\JwtCookieFactory::class)
        ->arg('$jwtTtl', '%app.jwt_ttl%')
        ->arg('$secure', '%app.cookie_secure%');

    $services->set(Service\IssueRefreshToken::class)
        ->arg('$refreshTokenTtl', '%app.refresh_token_ttl%');

    $services->set(RefreshToken\RandomRefreshTokenGenerator::class);
    $services->alias(RefreshToken\RefreshTokenGenerator::class, RefreshToken\RandomRefreshTokenGenerator::class);
};
