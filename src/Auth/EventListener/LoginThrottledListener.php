<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Records one security event per login lockout (not per failed attempt) so that
 * brute force and credential stuffing are visible in production logs.
 */
#[AsEventListener(event: LoginFailureEvent::class)]
#[WithMonologChannel('security')]
final class LoginThrottledListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        if (!$event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $request = $event->getRequest();

        $this->logger->warning('Login throttled.', [
            'ip' => $request->getClientIp(),
            'identifier' => $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME),
            'firewall' => $event->getFirewallName(),
        ]);
    }
}
