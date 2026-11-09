<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Stripe;

use App\Billing\Stripe\StripeClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;

final class StripeClientFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    #[Test]
    public function theClientShouldGiveUpOnStripeLongBeforeTheLibrarysDefaults(): void
    {
        $stripe = StripeClientFactory::create('sk_test_fake', 30);

        $httpClient = ApiRequestor::httpClient();
        self::assertInstanceOf(CurlClient::class, $httpClient);
        self::assertSame(3, $httpClient->getConnectTimeout());
        self::assertSame(30, $httpClient->getTimeout());
        self::assertSame(1, $stripe->getMaxNetworkRetries());
    }

    #[Test]
    public function theRequestTimeoutShouldBeTheOneGiven(): void
    {
        StripeClientFactory::create('sk_test_fake', 12);

        $httpClient = ApiRequestor::httpClient();
        self::assertInstanceOf(CurlClient::class, $httpClient);
        self::assertSame(12, $httpClient->getTimeout());
    }
}
