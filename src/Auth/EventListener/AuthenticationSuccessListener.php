<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Entity\User;
use App\Auth\Factory\JwtCookieFactory;
use App\Auth\Service\IssueRefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class AuthenticationSuccessListener
{
    public function __construct(
        private readonly IssueRefreshToken $issueRefreshToken,
        private readonly JwtCookieFactory $cookieFactory,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $refreshToken = $this->issueRefreshToken->handle($user);
        $this->em->flush();

        $event->getResponse()->headers->setCookie(
            $this->cookieFactory->createRefreshCookie($refreshToken)
        );
    }
}
