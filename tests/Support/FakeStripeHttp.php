<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/** Answers the two calls a page needs, as Stripe's API would, and keeps what was sent. */
final class FakeStripeHttp implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: list<string>, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{id: string, object: string}> */
    public array $prices = [['id' => 'price_found', 'object' => 'price']];

    public int $status = 200;

    public ?ApiConnectionException $failure = null;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        $isSession = str_contains($absUrl, '/v1/checkout/sessions');

        if ($isSession && null !== $this->failure) {
            throw $this->failure;
        }

        if ($isSession && 200 !== $this->status) {
            return [json_encode(['error' => ['type' => 'api_error', 'message' => 'Stripe answered '.$this->status]], JSON_THROW_ON_ERROR), $this->status, []];
        }

        $body = $isSession
            ? ['id' => 'cs_test_page', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_page']
            : ['object' => 'list', 'data' => $this->prices, 'has_more' => false, 'url' => '/v1/prices'];

        return [json_encode($body, JSON_THROW_ON_ERROR), 200, []];
    }

    /** @return array<string, mixed> */
    public function sessionParams(): array
    {
        return $this->requests[1]['params'];
    }

    public function idempotencyKey(int $request): ?string
    {
        foreach ($this->requests[$request]['headers'] as $header) {
            if (str_starts_with($header, 'Idempotency-Key: ')) {
                return substr($header, strlen('Idempotency-Key: '));
            }
        }

        return null;
    }
}
