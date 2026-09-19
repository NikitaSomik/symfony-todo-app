<?php

declare(strict_types=1);

namespace App\Auth\Security\Authentication\Lexik;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return JsonApiResponse::error(
                [new JsonApiError((string) Response::HTTP_TOO_MANY_REQUESTS, $this->tooManyAttemptsMessage($exception))],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    private function tooManyAttemptsMessage(TooManyLoginAttemptsAuthenticationException $exception): string
    {
        // Minutes until the lockout ends, rounded up by Symfony; unknown when not set.
        $minutes = (int) ($exception->getMessageData()['%minutes%'] ?? 0);

        if ($minutes <= 0) {
            return 'Too many login attempts. Please try again later.';
        }

        return sprintf('Too many login attempts. Please try again in %d minute%s.', $minutes, 1 === $minutes ? '' : 's');
    }
}
