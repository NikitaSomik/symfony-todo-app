<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

final class JwtFailureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_NOT_FOUND => 'onFailure',
            Events::JWT_INVALID => 'onFailure',
            Events::JWT_EXPIRED => 'onFailure',
        ];
    }

    public function onFailure(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(
            JsonApiResponse::error(
                [new JsonApiError((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
                Response::HTTP_UNAUTHORIZED,
            ),
        );
    }
}
