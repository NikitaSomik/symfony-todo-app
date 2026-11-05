<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;

final class StripeClientFactory
{
    private const int CONNECT_TIMEOUT_SECONDS = 3;
    private const int TIMEOUT_SECONDS = 10;

    public static function create(string $secretKey): StripeClient
    {
        // The library's defaults are 30 and 80 seconds, long enough to hold a PHP worker while
        // the customer waits. Its HTTP client is global, so it is set here, once, with the client.
        $httpClient = new CurlClient();
        $httpClient->setConnectTimeout(self::CONNECT_TIMEOUT_SECONDS);
        $httpClient->setTimeout(self::TIMEOUT_SECONDS);
        ApiRequestor::setHttpClient($httpClient);

        return new StripeClient(['api_key' => $secretKey, 'max_network_retries' => 1]);
    }
}
