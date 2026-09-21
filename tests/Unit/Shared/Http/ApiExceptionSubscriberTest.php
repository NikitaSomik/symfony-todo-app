<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\ApiExceptionSubscriber;
use App\Shared\Http\ClientFacingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionSubscriberTest extends TestCase
{
    #[Test]
    public function clientFacingExceptionShouldKeepItsMessage(): void
    {
        $domain = new class('Email is already taken.') extends \DomainException implements ClientFacingException {};

        $response = $this->handle(new HttpException(409, $domain->getMessage(), $domain));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['status' => '409', 'message' => 'Email is already taken.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function unmarkedHttpExceptionShouldUseGenericMessage(): void
    {
        $internal = new \RuntimeException('SQLSTATE[08006] connection to database failed');

        $response = $this->handle(new HttpException(409, $internal->getMessage(), $internal));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['status' => '409', 'message' => 'Conflict']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function unmappedExceptionShouldBecomeServerError(): void
    {
        $response = $this->handle(new \RuntimeException('SQLSTATE[08006] connection to database failed'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['status' => '500', 'message' => 'Server Error.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function knownHttpExceptionShouldUseItsOwnText(): void
    {
        $response = $this->handle(new NotFoundHttpException('No route found for "GET /api/v1/ghost"'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['status' => '404', 'message' => 'Not Found.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    private function handle(\Throwable $throwable): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/v1/auth/register', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        (new ApiExceptionSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }
}
