<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/** Answers the calls Billing makes, as Stripe's API would, and keeps what was sent. */
final class FakeStripeHttp implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{id: string, object: string}> */
    public array $prices = [['id' => 'price_found', 'object' => 'price']];

    /** @var list<array{id: string, object: string, status: string}> */
    public array $subscriptions = [];

    public ?string $checkoutUrl = 'https://checkout.stripe.com/c/pay/cs_test_page';

    /** @var array<string, string> the customer's Checkout sessions, by id, with their status */
    public array $checkoutSessions = [];

    /** @var array<string, string> sessions Stripe refuses to expire, with the status it reports for them afterwards */
    public array $refusedExpiries = [];

    public int $checkoutSessionsPerPage = 100;

    /** Limits $status and $failure to the requests whose path ends with it; null applies them to every request. */
    public ?string $failingPath = null;

    public int $status = 200;

    public string $requestId = 'req_fake';

    public ?ApiConnectionException $failure = null;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $fails = null === $this->failingPath || str_ends_with($path, $this->failingPath);

        if ($fails && null !== $this->failure) {
            throw $this->failure;
        }

        if ($fails && 200 !== $this->status) {
            return [$this->error($this->status), $this->status, $this->headers()];
        }

        $expiredId = $this->expiredSessionId($method, $path);

        if (null !== $expiredId && array_key_exists($expiredId, $this->refusedExpiries)) {
            $this->checkoutSessions[$expiredId] = $this->refusedExpiries[$expiredId];

            return [$this->error(400), 400, $this->headers()];
        }

        return [json_encode($this->answer($method, $path, $params), JSON_THROW_ON_ERROR), 200, $this->headers()];
    }

    public function countSentTo(string $path): int
    {
        $sent = array_filter($this->requests, static fn (array $request): bool => str_ends_with((string) parse_url($request['url'], PHP_URL_PATH), $path));

        return count($sent);
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

    /** Stripe's own client hands its headers over in this case-insensitive form. */
    private function headers(): CaseInsensitiveArray
    {
        return new CaseInsensitiveArray(['Request-Id' => $this->requestId]);
    }

    private function error(int $status): string
    {
        return json_encode(['error' => ['type' => 'api_error', 'code' => 'fake_failure', 'message' => 'Stripe answered '.$status]], JSON_THROW_ON_ERROR);
    }

    private function expiredSessionId(string $method, string $path): ?string
    {
        if ('post' !== strtolower($method) || 1 !== preg_match('{^/v1/checkout/sessions/([^/]+)/expire$}', $path, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function answer(string $method, string $path, array $params): array
    {
        $expiredId = $this->expiredSessionId($method, $path);

        if (null !== $expiredId) {
            $this->checkoutSessions[$expiredId] = 'expired';

            return $this->checkoutSession($expiredId);
        }

        if (1 === preg_match('{^/v1/checkout/sessions/([^/]+)$}', $path, $matches)) {
            return $this->checkoutSession($matches[1]);
        }

        return match ([strtolower($method), $path]) {
            ['post', '/v1/customers'] => ['id' => 'cus_new', 'object' => 'customer'],
            ['get', '/v1/subscriptions'] => ['object' => 'list', 'data' => $this->subscriptions, 'has_more' => false, 'url' => $path],
            ['get', '/v1/prices'] => ['object' => 'list', 'data' => $this->prices, 'has_more' => false, 'url' => $path],
            ['get', '/v1/checkout/sessions'] => $this->openCheckoutSessions($path, $params),
            ['post', '/v1/checkout/sessions'] => ['id' => 'cs_test_page', 'object' => 'checkout.session', 'url' => $this->checkoutUrl],
            default => throw new \LogicException(sprintf('Billing does not call %s %s.', $method, $path)),
        };
    }

    /** @return array<string, mixed> */
    private function checkoutSession(string $id): array
    {
        return ['id' => $id, 'object' => 'checkout.session', 'status' => $this->checkoutSessions[$id] ?? throw new \LogicException(sprintf('No session %s.', $id))];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function openCheckoutSessions(string $path, array $params): array
    {
        $ids = array_keys($this->checkoutSessions);
        $after = $params['starting_after'] ?? null;

        if (is_string($after)) {
            $ids = array_slice($ids, (int) array_search($after, $ids, true) + 1);
        }

        $ids = array_values(array_filter($ids, fn (string $id): bool => 'open' === $this->checkoutSessions[$id]));

        $page = array_slice($ids, 0, $this->checkoutSessionsPerPage);

        return [
            'object' => 'list',
            'data' => array_map($this->checkoutSession(...), $page),
            'has_more' => count($ids) > count($page),
            'url' => $path,
        ];
    }
}
