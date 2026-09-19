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
        // The message never mentions the account: the response is the same whether the email exists or not.
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return JsonApiResponse::error(
                [new JsonApiError((string) Response::HTTP_TOO_MANY_REQUESTS, 'Too many login attempts. Please try again later.')],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
