<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Factory\JwtCookieFactory;
use App\Auth\Repository\RefreshTokenRepository;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JwtCookieFactory $cookieFactory,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $refreshTokenValue = $event->getRequest()->cookies->get(JwtCookieFactory::REFRESH_COOKIE);

        if (null !== $refreshTokenValue) {
            $this->revokeUserRefreshTokens($refreshTokenValue);
        }

        $event->setResponse($this->logoutResponse());
    }

    private function revokeUserRefreshTokens(string $refreshTokenValue): void
    {
        $storedRefreshToken = $this->refreshTokenRepository->findValidByToken($refreshTokenValue);

        if (null !== $storedRefreshToken) {
            $this->refreshTokenRepository->deleteAllForUser($storedRefreshToken->getUser());
        }
    }

    private function logoutResponse(): JsonApiResponse
    {
        $response = JsonApiResponse::noContent();
        $response->headers->setCookie($this->cookieFactory->clearJwtCookie());
        $response->headers->setCookie($this->cookieFactory->clearRefreshCookie());
        $response->headers->set('Clear-Site-Data', '"cookies"');

        return $response;
    }
}
