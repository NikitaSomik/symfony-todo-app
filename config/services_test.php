<?php

declare(strict_types=1);

use App\Billing\Payment\PaymentProvider;
use App\Tests\Support\AuditLogFailureToggle;
use App\Tests\Support\ConcurrentTokenExchange;
use App\Tests\Support\FailAuditLogEventSubscriber;
use App\Tests\Support\PaymentProviderStub;
use App\Tests\Support\PwnedPasswordsStub;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $di): void {
    $di->services()
        ->set(AuditLogFailureToggle::class)
        ->set(FailAuditLogEventSubscriber::class)
        ->args([service(AuditLogFailureToggle::class)])
        ->tag('kernel.event_subscriber')
        ->set(ConcurrentTokenExchange::class)
        ->args([service('doctrine.dbal.default_connection')])
        ->tag('doctrine.event_listener', ['event' => 'postLoad'])
        ->set(PwnedPasswordsStub::class)
        ->set(PaymentProviderStub::class)
        ->alias(PaymentProvider::class, PaymentProviderStub::class)->public();
};
