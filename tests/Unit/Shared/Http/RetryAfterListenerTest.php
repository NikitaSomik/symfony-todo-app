<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\RetryableException;
use App\Shared\Http\RetryAfterListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RetryAfterListenerTest extends TestCase
{
    #[Test]
    public function mappedRetryableExceptionShouldAddRetryAfter(): void
    {
        $event = $this->eventFor(new HttpException(503, 'Down.', self::retryable(30), ['X-Kept' => 'yes']));

        (new RetryAfterListener())($event);

        self::assertSame(['X-Kept' => 'yes', 'Retry-After' => '30'], $this->headersOf($event));
    }

    #[Test]
    public function retryableHttpExceptionShouldAddRetryAfter(): void
    {
        $exception = new class(503, 'Down.') extends HttpException implements RetryableException {
            public function retryAfterSeconds(): int
            {
                return 15;
            }
        };
        $event = $this->eventFor($exception);

        (new RetryAfterListener())($event);

        self::assertSame(['Retry-After' => '15'], $this->headersOf($event));
    }

    #[Test]
    public function otherExceptionShouldGetNoRetryAfter(): void
    {
        $event = $this->eventFor(new HttpException(503, 'Down.', new \RuntimeException('Down.')));

        (new RetryAfterListener())($event);

        self::assertSame([], $this->headersOf($event));
    }

    #[Test]
    public function unmappedRetryableExceptionShouldStayAsItIs(): void
    {
        $exception = self::retryable(30);
        $event = $this->eventFor($exception);

        (new RetryAfterListener())($event);

        self::assertSame($exception, $event->getThrowable());
    }

    private static function retryable(int $seconds): \RuntimeException
    {
        return new class($seconds) extends \RuntimeException implements RetryableException {
            public function __construct(private readonly int $seconds)
            {
                parent::__construct('Down.');
            }

            public function retryAfterSeconds(): int
            {
                return $this->seconds;
            }
        };
    }

    private function eventFor(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    /** @return array<string, string> */
    private function headersOf(ExceptionEvent $event): array
    {
        $throwable = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $throwable);

        return $throwable->getHeaders();
    }
}
