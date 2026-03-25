<?php

declare(strict_types=1);

namespace App\Auth\Security\EntryPoint;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class ApiAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return JsonApiResponse::error(
            [new JsonApiError((string) Response::HTTP_UNAUTHORIZED, 'Unauthorized.')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
