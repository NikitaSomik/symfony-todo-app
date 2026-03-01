<?php

declare(strict_types=1);

namespace App\Tests;

use App\Auth\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
abstract class ApiTestCase extends WebTestCase
{
    private KernelBrowser $client;
    private ?string $token = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = null;
    }

    protected function actingAs(User $user): static
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $this->token = $jwtManager->create($user);

        return $this;
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

    protected function delete(string $uri): Response
    {
        return $this->request('DELETE', $uri);
    }

    protected function json(Response $response): array
    {
        return json_decode($response->getContent(), true) ?? [];
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
        $server = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $this->token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: [] !== $body ? json_encode($body) : null,
        );

        return $this->client->getResponse();
    }
}
