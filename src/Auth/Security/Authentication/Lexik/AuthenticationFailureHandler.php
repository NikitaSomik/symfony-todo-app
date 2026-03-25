<?php

declare(strict_types=1);

namespace App\Auth\Security\Authentication\Lexik;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
