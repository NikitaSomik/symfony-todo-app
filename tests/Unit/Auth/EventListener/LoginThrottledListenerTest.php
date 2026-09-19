<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\EventListener;

use App\Auth\EventListener\LoginThrottledListener;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginThrottledListenerTest extends TestCase
{
    private TestHandler $logs;
    private LoginThrottledListener $listener;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->listener = new LoginThrottledListener(new Logger('security', [$this->logs]));
    }

    #[Test]
    public function invokeWhenLoginIsThrottledShouldLogWarningWithContext(): void
    {
        ($this->listener)($this->failureEvent(new TooManyLoginAttemptsAuthenticationException(1)));

        self::assertCount(1, $this->logs->getRecords());

        $record = $this->logs->getRecords()[0];
        self::assertSame(Level::Warning, $record->level);
        self::assertSame('Login throttled.', $record->message);
        self::assertSame([
            'ip' => '203.0.113.10',
            'identifier' => 'user@example.com',
            'firewall' => 'auth',
        ], $record->context);
    }

    #[Test]
    public function invokeWhenLoginFailsForAnotherReasonShouldNotLog(): void
    {
        ($this->listener)($this->failureEvent(new BadCredentialsException()));

        self::assertSame([], $this->logs->getRecords());
    }

    private function failureEvent(AuthenticationException $exception): LoginFailureEvent
    {
        $request = Request::create('/api/v1/auth/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'user@example.com');

        return new LoginFailureEvent(
            $exception,
            $this->createStub(AuthenticatorInterface::class),
            $request,
            null,
            'auth',
        );
    }
}
