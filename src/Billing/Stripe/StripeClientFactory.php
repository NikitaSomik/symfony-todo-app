<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;

final class StripeClientFactory
{
    private const int CONNECT_TIMEOUT_SECONDS = 3;

    public static function create(string $secretKey, int $timeoutSeconds): StripeClient
    {
        // The library's connect timeout is 30 seconds, long enough to hold a PHP worker while
        // the customer waits. Its HTTP client is global, so it is set here, once, with the client.
        $httpClient = new CurlClient();
        $httpClient->setConnectTimeout(self::CONNECT_TIMEOUT_SECONDS);
        $httpClient->setTimeout($timeoutSeconds);
        ApiRequestor::setHttpClient($httpClient);

        return new StripeClient(['api_key' => $secretKey, 'max_network_retries' => 1]);
    }
}
