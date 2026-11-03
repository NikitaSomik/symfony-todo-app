<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Service\WorkspacePlanLimits;
use App\Workspace\Contract\PlanLimits;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\Billing\\', __DIR__)
            ->exclude([__DIR__.'/{di,routing}.php', __DIR__.'/Entity'])
        ->set(WorkspacePlanLimits::class)
            ->arg('$mode', '%env(enum:App\Billing\Enum\LimitMode:BILLING_LIMITS)%')
        ->alias(PlanLimits::class, WorkspacePlanLimits::class);
};
