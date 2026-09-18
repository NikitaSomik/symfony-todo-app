<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Factory\JwtCookieFactory;
use App\Auth\Service\RevokeRefreshTokens;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __construct(
        private readonly RevokeRefreshTokens $revokeRefreshTokens,
        private readonly JwtCookieFactory $cookieFactory,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $refreshTokenValue = $event->getRequest()->cookies->get(JwtCookieFactory::REFRESH_COOKIE);

        if (null !== $refreshTokenValue) {
            $this->revokeRefreshTokens->handle($refreshTokenValue);
        }

        $event->setResponse($this->logoutResponse());
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
