<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Exception\RefreshTokenReusedException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
#[WithMonologChannel('security')]
final readonly class RefreshTokenReuseListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof RefreshTokenReusedException) {
            return;
        }

        $this->logger->warning('Refresh token reused, session revoked.', [
            'user_id' => $exception->userId,
            'family_id' => $exception->familyId->toRfc4122(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }
}
