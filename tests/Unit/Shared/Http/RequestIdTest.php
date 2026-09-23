<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\RequestId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class RequestIdTest extends TestCase
{
    private const string PROXY = '10.0.0.1';

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
    }

    #[Test]
    public function requestWithoutAnIdShouldGetANewUuid(): void
    {
        $id = RequestId::of(Request::create('/'));

        self::assertTrue(Uuid::isValid($id));
    }

    #[Test]
    public function idShouldStayTheSameForTheWholeRequest(): void
    {
        $request = Request::create('/');

        self::assertSame(RequestId::of($request), RequestId::of($request));
    }

    #[Test]
    public function idFromAnUntrustedClientShouldBeIgnored(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_REQUEST_ID' => 'chosen-by-client']);

        self::assertNotSame('chosen-by-client', RequestId::of($request));
    }

    #[Test]
    public function idFromATrustedProxyShouldBeKept(): void
    {
        Request::setTrustedProxies([self::PROXY], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('/', server: ['REMOTE_ADDR' => self::PROXY, 'HTTP_X_REQUEST_ID' => 'a1b2c3.d4-e5_f6']);

        self::assertSame('a1b2c3.d4-e5_f6', RequestId::of($request));
    }

    /**
     * Even a trusted proxy cannot put a line break or an oversized value into the logs.
     */
    #[Test]
    #[TestWith(["abc\nforged log line"])]
    #[TestWith(['with space'])]
    #[TestWith([''])]
    public function malformedIdFromATrustedProxyShouldBeReplaced(string $incoming): void
    {
        Request::setTrustedProxies([self::PROXY], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('/', server: ['REMOTE_ADDR' => self::PROXY, 'HTTP_X_REQUEST_ID' => $incoming]);

        self::assertTrue(Uuid::isValid(RequestId::of($request)));
    }

    #[Test]
    public function tooLongIdFromATrustedProxyShouldBeReplaced(): void
    {
        Request::setTrustedProxies([self::PROXY], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('/', server: ['REMOTE_ADDR' => self::PROXY, 'HTTP_X_REQUEST_ID' => str_repeat('a', 129)]);

        self::assertTrue(Uuid::isValid(RequestId::of($request)));
    }
}
