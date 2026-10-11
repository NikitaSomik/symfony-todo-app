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
        $previous = $exception->getPrevious();

        return match (true) {
            $previous instanceof JWTDecodeFailureException => match ($previous->getReason()) {
                JWTDecodeFailureException::UNVERIFIED_TOKEN => 'bad_signature',
                default => 'malformed',
            },
            // Lexik's blocklist throws this message for a token revoked at logout.
            'JWT blocked' === $exception->getMessage() => 'revoked',
            $exception instanceof InvalidPayloadException => 'invalid_payload',
            // Symfony hides it behind BadCredentialsException, so the client cannot probe for users.
            $exception instanceof UserNotFoundException, $previous instanceof UserNotFoundException => 'user_not_found',
            default => 'invalid',
        };
    }
}
