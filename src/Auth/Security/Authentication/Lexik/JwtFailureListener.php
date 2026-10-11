<?php

declare(strict_types=1);

namespace App\Auth\Security\Authentication\Lexik;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener(event: Events::JWT_NOT_FOUND)]
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class JwtFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(
            JsonApiResponse::error(
                [JsonApiError::of((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
                Response::HTTP_UNAUTHORIZED,
            ),
        );
    }
}
