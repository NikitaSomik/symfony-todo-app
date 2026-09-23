<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\RequestId;
use App\Shared\Http\RequestIdListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestIdListenerTest extends TestCase
{
    #[Test]
    public function errorDocumentShouldQuoteTheIdBetweenJsonapiAndErrors(): void
    {
        $request = Request::create('/api/v1/tasks');
        $response = $this->respond($request, new JsonResponse(['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '404']]], 404));

        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'meta' => ['request_id' => RequestId::of($request)], 'errors' => [['status' => '404']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function existingMetaShouldBeKept(): void
    {
        $request = Request::create('/api/v1/tasks');
        $response = $this->respond($request, new JsonResponse(['meta' => ['retry_after' => 60], 'errors' => [['status' => '429']]], 429));

        self::assertSame(
            ['request_id' => RequestId::of($request), 'retry_after' => 60],
            json_decode((string) $response->getContent(), true)['meta'],
        );
    }

    #[Test]
    public function successfulResponseShouldOnlyGetTheHeader(): void
    {
        $response = $this->respond(Request::create('/api/v1/tasks'), new JsonResponse(['data' => []]));

        self::assertSame(['data' => []], json_decode((string) $response->getContent(), true));
        self::assertNotNull($response->headers->get(RequestId::HEADER));
    }

    #[Test]
    public function errorThatIsNotJsonShouldOnlyGetTheHeader(): void
    {
        $response = $this->respond(Request::create('/'), new Response('<h1>Not Found</h1>', 404, ['Content-Type' => 'text/html']));

        self::assertSame('<h1>Not Found</h1>', $response->getContent());
        self::assertNotNull($response->headers->get(RequestId::HEADER));
    }

    private function respond(Request $request, Response $response): Response
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        (new RequestIdListener())(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
