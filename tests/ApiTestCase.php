<?php

declare(strict_types=1);

namespace App\Tests;

use App\Auth\Entity\User;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
abstract class ApiTestCase extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Login throttling counters are persisted in tests; stop them leaking into the next test.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function fromIp(string $ip): static
    {
        $this->client->setServerParameter('REMOTE_ADDR', $ip);

        return $this;
    }

    protected function withForwardedFor(string $ip): static
    {
        $this->client->setServerParameter('HTTP_X_FORWARDED_FOR', $ip);

        return $this;
    }

    protected function accepting(string $mimeType): static
    {
        $this->client->setServerParameter('HTTP_ACCEPT', $mimeType);

        return $this;
    }

    protected function withHeader(string $name, string $value): static
    {
        $this->client->setServerParameter('HTTP_'.strtoupper(str_replace('-', '_', $name)), $value);

        return $this;
    }

    protected function actingAs(User $user): static
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $token = $jwtManager->create($user);
        $this->client->getCookieJar()->set(new Cookie('access_token', $token));

        return $this;
    }

    protected function setCookie(string $name, string $value): static
    {
        $this->client->getCookieJar()->set(new Cookie($name, $value));

        return $this;
    }

    protected function setCookieWithPath(string $name, string $value, string $path): static
    {
        $this->client->getCookieJar()->set(new Cookie($name, $value, path: $path));

        return $this;
    }

    /** Collects the profile of the next request only; read it with executedSql(). */
    protected function withProfiler(): static
    {
        $this->client->enableProfiler();
        // Fixtures run in the same kernel before the first request; keep their queries out of the profile.
        static::getContainer()->get('doctrine.debug_data_holder')->reset();

        return $this;
    }

    /** @return list<string> SQL of the last profiled request, in execution order */
    protected function executedSql(): array
    {
        $collector = $this->client->getProfile()->getCollector('db');
        \assert($collector instanceof DoctrineDataCollector);

        return array_column($collector->getQueries()['default'] ?? [], 'sql');
    }

    protected function get(string $uri): Response
    {
        return $this->request('GET', $uri);
    }

    protected function post(string $uri, array $body = []): Response
    {
        return $this->request('POST', $uri, $body);
    }

    protected function put(string $uri, array $body = []): Response
    {
        return $this->request('PUT', $uri, $body);
    }

    /**
     * Sends a body as it is, with the given Content-Type, or with none when it is null.
     */
    protected function sendRaw(string $method, string $uri, ?string $contentType, string $content): Response
    {
        $this->client->request($method, $uri, server: null === $contentType ? [] : ['CONTENT_TYPE' => $contentType], content: $content);

        return $this->client->getResponse();
    }

    protected function delete(string $uri): Response
    {
        return $this->request('DELETE', $uri);
    }

    protected function json(Response $response): array
    {
        return json_decode($response->getContent(), true) ?? [];
    }

    /** Extract the "data" key from a JSON:API response. */
    protected function jsonData(Response $response): array
    {
        return $this->json($response)['data'] ?? [];
    }

    /** Extract attributes from a single-resource JSON:API response. */
    protected function jsonAttributes(Response $response): array
    {
        return $this->jsonData($response)['attributes'] ?? [];
    }

    protected function assertJsonContains(array $expected, Response $response): void
    {
        $data = $this->json($response);

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $data);
            self::assertSame($value, $data[$key]);
        }
    }

    protected function route(string $name, array $params = []): string
    {
        return $this->getContainer()->get('router')->generate($name, $params);
    }

    private function request(string $method, string $uri, array $body = []): Response
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: [] !== $body ? json_encode($body) : null,
        );

        return $this->client->getResponse();
    }
}
