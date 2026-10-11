<?php

declare(strict_types=1);

namespace App\Auth\Security\Authentication\Lexik;

use App\Shared\Api\JsonApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// A request without a token is answered by ApiAuthenticationEntryPoint, the firewall's entry point.
// JWT_NOT_FOUND is reached only if a firewall has no entry point of its own: then this keeps the answer ours.
#[AsEventListener(event: Events::JWT_NOT_FOUND)]
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class JwtFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(JsonApiResponse::unauthorized());
    }
}
