<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Payment\PaymentProvider;
use App\Billing\Service\WorkspacePlanLimits;
use App\Billing\Stripe\StripeClientFactory;
use App\Billing\Stripe\StripePaymentProvider;
use App\Workspace\Contract\PlanLimits;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('App\Billing\\', __DIR__)
            ->exclude([
                __DIR__.'/DTO/',
                __DIR__.'/Entity/',
                __DIR__.'/Enum/',
                __DIR__.'/Payment/CheckoutPage.php',
                __DIR__.'/Security/WorkspaceContext.php',
                __DIR__.'/Stripe/StripeClientFactory.php',
                __DIR__.'/{di,routing}.php',
            ])
        ->set(StripeClient::class)
            ->factory([StripeClientFactory::class, 'create'])
            ->args(['%env(STRIPE_SECRET_KEY)%', '%env(int:STRIPE_TIMEOUT_SECONDS)%'])
        ->alias(PaymentProvider::class, StripePaymentProvider::class)
        ->alias(PlanLimits::class, WorkspacePlanLimits::class);
};
