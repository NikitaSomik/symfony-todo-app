<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/** Answers the calls Billing makes, as Stripe's API would, and keeps what was sent. */
final class FakeStripeHttp implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{id: string, object: string}> */
    public array $prices = [['id' => 'price_found', 'object' => 'price']];

    /** @var list<array{id: string, object: string, status: string}> */
    public array $subscriptions = [];

    public int $status = 200;

    public ?ApiConnectionException $failure = null;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        if (null !== $this->failure) {
            throw $this->failure;
        }

        if (200 !== $this->status) {
            return [json_encode(['error' => ['type' => 'api_error', 'message' => 'Stripe answered '.$this->status]], JSON_THROW_ON_ERROR), $this->status, []];
        }

        return [json_encode($this->answer($absUrl), JSON_THROW_ON_ERROR), 200, []];
    }

    /** @return array<string, mixed> the params of the last request to a path such as "/v1/customers" */
    public function sentTo(string $path): array
    {
        foreach (array_reverse($this->requests) as $request) {
            if (str_ends_with((string) parse_url($request['url'], PHP_URL_PATH), $path)) {
                return $request['params'];
            }
        }

        throw new \LogicException(sprintf('Nothing was sent to %s.', $path));
    }

    /** @return array<string, mixed> */
    private function answer(string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match ($path) {
            '/v1/customers' => ['id' => 'cus_new', 'object' => 'customer'],
            '/v1/subscriptions' => ['object' => 'list', 'data' => $this->subscriptions, 'has_more' => false, 'url' => $path],
            '/v1/prices' => ['object' => 'list', 'data' => $this->prices, 'has_more' => false, 'url' => $path],
            '/v1/checkout/sessions' => ['id' => 'cs_test_page', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_page'],
            default => throw new \LogicException(sprintf('Billing does not call %s.', $path)),
        };
    }
}
