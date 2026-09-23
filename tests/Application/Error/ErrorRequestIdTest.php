<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Error responses are built in several places — the exception normalizer and each security handler —
 * and every one of them must still carry the request id header, so the error can be found in the logs.
 */
final class ErrorRequestIdTest extends ApiTestCase
{
    #[Test]
    public function missingTokenShouldCarryTheRequestId(): void
    {
        $this->assertRequestIdHeader($this->get($this->route('api_profile_me')), 401);
    }

    #[Test]
    public function invalidTokenShouldCarryTheRequestId(): void
    {
        $this->setCookie('access_token', 'not-a-jwt');

        $this->assertRequestIdHeader($this->get($this->route('api_profile_me')), 401);
    }

    #[Test]
    public function failedLoginShouldCarryTheRequestId(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->assertRequestIdHeader(
            $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => 'wrong']),
            401,
        );
    }

    #[Test]
    public function invalidRefreshTokenShouldCarryTheRequestId(): void
    {
        $this->setCookie('refresh_token', 'invalid-token-value');

        $this->assertRequestIdHeader($this->post($this->route('api_auth_refresh')), 401);
    }

    #[Test]
    public function forbiddenResourceShouldCarryTheRequestId(): void
    {
        $task = TaskFactory::createOne(['user' => UserFactory::createOne()]);
        $this->actingAs(UserFactory::createOne());

        $this->assertRequestIdHeader($this->get($this->route('api_task_get', ['id' => $task->getId()->toRfc4122()])), 403);
    }

    #[Test]
    public function validationErrorShouldCarryTheRequestId(): void
    {
        $this->assertRequestIdHeader(
            $this->post($this->route('api_auth_register'), ['email' => 'not-an-email', 'password' => '1']),
            422,
        );
    }

    #[Test]
    public function serverErrorShouldCarryTheRequestId(): void
    {
        $this->actingAs(UserFactory::createOne());
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $this->assertRequestIdHeader($this->post($this->route('api_task_create'), ['title' => 'Buy milk']), 500);
    }

    private function assertRequestIdHeader(Response $response, int $status): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertTrue(Uuid::isValid((string) $response->headers->get('X-Request-Id')));
    }
}
