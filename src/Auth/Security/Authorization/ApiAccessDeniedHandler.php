<?php

declare(strict_types=1);

namespace App\Auth\Security\Authorization;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

final class ApiAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return JsonApiResponse::error(
            [JsonApiError::of((string) Response::HTTP_FORBIDDEN, 'Forbidden.')],
            Response::HTTP_FORBIDDEN,
        );
    }
}
