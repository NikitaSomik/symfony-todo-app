<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Security\Authentication\Lexik;

use App\Auth\Security\Authentication\Lexik\AuthenticationFailureHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class AuthenticationFailureHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, string}>
     */
    public static function throttledMessages(): iterable
    {
        yield 'one minute' => [1, 'Too many login attempts. Please try again in 1 minute.'];
        yield 'several minutes' => [5, 'Too many login attempts. Please try again in 5 minutes.'];
        yield 'unknown time' => [null, 'Too many login attempts. Please try again later.'];
    }

    #[Test]
    #[DataProvider('throttledMessages')]
    public function onAuthenticationFailureWhenThrottledShouldReturn429WithRetryTime(?int $minutes, string $message): void
    {
        $response = $this->handle(new TooManyLoginAttemptsAuthenticationException($minutes));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '429', 'detail' => $message]]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function onAuthenticationFailureWhenCredentialsAreInvalidShouldReturn401(): void
    {
        $response = $this->handle(new BadCredentialsException());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '401', 'detail' => 'Unauthorized.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    private function handle(AuthenticationException $exception): Response
    {
        return (new AuthenticationFailureHandler())->onAuthenticationFailure(Request::create('/api/v1/auth/login', 'POST'), $exception);
    }
}
