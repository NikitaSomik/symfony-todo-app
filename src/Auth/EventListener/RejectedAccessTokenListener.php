<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[AsEventListener(event: Events::JWT_INVALID)]
#[WithMonologChannel('security')]
final readonly class RejectedAccessTokenListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(JWTInvalidEvent $event): void
    {
        $exception = $event->getException();

        $this->logger->warning('Access token rejected.', [
            'reason' => $this->reason($exception),
            'ip' => $event->getRequest()?->getClientIp(),
        ]);
    }

    private function reason(AuthenticationException $exception): string
    {
        $cause = $exception->getPrevious();

        if ($cause instanceof JWTDecodeFailureException) {
            return JWTDecodeFailureException::UNVERIFIED_TOKEN === $cause->getReason() ? 'bad_signature' : 'malformed';
        }

        // Lexik has no exception type for a token revoked at logout, only this message.
        if ('JWT blocked' === $exception->getMessage()) {
            return 'revoked';
        }

        // Symfony wraps it in BadCredentialsException, so the client cannot probe for users.
        if ($cause instanceof UserNotFoundException) {
            return 'user_not_found';
        }

        if ($exception instanceof InvalidPayloadException) {
            return 'invalid_payload';
        }

        return 'invalid';
    }
}
