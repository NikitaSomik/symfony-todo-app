<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Entity\User;
use App\Auth\Factory\JwtCookieFactory;
use App\Auth\Repository\RefreshTokenRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
        $user = $event->getToken()?->getUser();

        if ($user instanceof User) {
            $this->refreshTokenRepository->deleteAllForUser($user);
        }

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clearJwtCookie());
        $response->headers->setCookie($this->cookieFactory->clearRefreshCookie());
        $response->headers->set('Clear-Site-Data', '"cookies"');

        $event->setResponse($response);
    }
}
