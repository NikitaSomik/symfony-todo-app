<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every error document quotes the id of its request, so an error a user reports can be found in the logs.
 * Each test goes through a different place that builds error documents.
 */
final class ErrorRequestIdTest extends ApiTestCase
{
    #[Test]
    public function missingTokenShouldQuoteTheRequestId(): void
    {
        $this->assertRequestIdQuoted($this->get($this->route('api_profile_me')), 401);
    }

    #[Test]
    public function invalidTokenShouldQuoteTheRequestId(): void
    {
        $this->setCookie('access_token', 'not-a-jwt');

        $this->assertRequestIdQuoted($this->get($this->route('api_profile_me')), 401);
    }

    #[Test]
    public function failedLoginShouldQuoteTheRequestId(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->assertRequestIdQuoted(
            $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => 'wrong']),
            401,
        );
    }

    #[Test]
    public function invalidRefreshTokenShouldQuoteTheRequestId(): void
    {
        $this->setCookie('refresh_token', 'invalid-token-value');

        $this->assertRequestIdQuoted($this->post($this->route('api_auth_refresh')), 401);
    }

    #[Test]
    public function forbiddenResourceShouldQuoteTheRequestId(): void
    {
        $task = TaskFactory::createOne(['user' => UserFactory::createOne()]);
        $this->actingAs(UserFactory::createOne());

        $this->assertRequestIdQuoted($this->get($this->route('api_task_get', ['id' => $task->getId()->toRfc4122()])), 403);
    }

    #[Test]
    public function validationErrorShouldQuoteTheRequestId(): void
    {
        $this->assertRequestIdQuoted(
            $this->post($this->route('api_auth_register'), ['email' => 'not-an-email', 'password' => '1']),
            422,
        );
    }

    #[Test]
    public function serverErrorShouldQuoteTheRequestId(): void
    {
        $this->actingAs(UserFactory::createOne());
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->assertRequestIdQuoted($this->post($this->route('api_task_create'), ['title' => 'Buy milk']), 500);
    }

    private function assertRequestIdQuoted(Response $response, int $status): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertNotNull($response->headers->get('X-Request-Id'));
        self::assertSame($response->headers->get('X-Request-Id'), $this->json($response)['meta']['request_id'] ?? null);
    }
}
