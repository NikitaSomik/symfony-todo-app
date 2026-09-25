<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Api\Documentation\UserResponseSchema;
use App\Auth\DTO\RegisterDTO;
use App\Auth\Exception\InvalidRefreshTokenException;
use App\Auth\Factory\JwtCookieFactory;
use App\Auth\Resource\UserResource;
use App\Auth\Service\RefreshAccessToken;
use App\Auth\Service\RegisterUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth', name: 'api_auth_', format: 'json')]
#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly RefreshAccessToken $refreshAccessToken,
        private readonly JwtCookieFactory $cookieFactory,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(summary: 'Register a new user', security: [])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: RegisterDTO::class)))]
    #[OA\Response(response: 201, description: 'User registered', content: new JsonApiContent(ref: new Model(type: UserResponseSchema::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(response: 409, description: 'Email already taken')]
    #[OA\Response(response: 429, description: 'Too many registration attempts')]
    #[RateLimit('registration')]
    public function register(#[MapRequestPayload(acceptFormat: 'json')] RegisterDTO $dto): JsonResponse
    {
        $user = $this->registerUser->handle($dto);

        return JsonApiResponse::one(UserResource::toItem($user), Response::HTTP_CREATED);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    #[OA\Post(summary: 'Refresh JWT token using refresh token cookie', security: [])]
    #[OA\Parameter(
        name: 'refresh_token',
        description: 'Refresh token cookie (set automatically by login/refresh)',
        in: 'cookie',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(
        response: 204,
        description: 'Tokens refreshed — new access_token and refresh_token cookies set',
        headers: [
            new OA\Header(header: 'Set-Cookie', description: 'Updated access_token and refresh_token HttpOnly cookies', schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(response: 401, description: 'Invalid or expired refresh token')]
    public function refresh(Request $request): Response
    {
        $refreshTokenValue = $request->cookies->get(JwtCookieFactory::REFRESH_COOKIE);

        if (null === $refreshTokenValue || strlen($refreshTokenValue) < 3 || strlen($refreshTokenValue) > 255) {
            throw new InvalidRefreshTokenException();
        }

        ['jwt' => $jwt, 'refreshToken' => $newRefreshToken] = $this->refreshAccessToken->handle($refreshTokenValue);

        $response = JsonApiResponse::noContent();
        $response->headers->setCookie($this->cookieFactory->createJwtCookie($jwt));
        $response->headers->setCookie($this->cookieFactory->createRefreshCookie($newRefreshToken));

        return $response;
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[OA\Post(summary: 'Logout — revoke refresh token and clear cookies')]
    #[OA\Parameter(
        name: 'refresh_token',
        description: 'Refresh token cookie used to revoke the current authenticated session on logout',
        in: 'cookie',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(
        response: 204,
        description: 'Logged out — cookies cleared and refresh token revoked when a valid refresh_token cookie is present',
    )]
    public function logout(): never
    {
        // This method is never executed — the security firewall intercepts the request.
        // The route exists for Symfony routing (security.yaml logout path) and OpenAPI docs.
        // Actual logic is in LogoutListener.
        throw new \LogicException('Intercepted by the security firewall.');
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(summary: 'Login — sets access_token and refresh_token cookies', security: [])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secret123'),
            ]
        )
    )]
    #[OA\Response(
        response: 204,
        description: 'Authenticated — access_token and refresh_token cookies set, empty response body',
        headers: [
            new OA\Header(header: 'Set-Cookie', description: 'access_token and refresh_token HttpOnly cookies', schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function login(): never
    {
        // This method is never executed — json_login authenticator intercepts the request.
        // The route exists for Symfony routing (security.yaml json_login check_path) and OpenAPI docs.
        // On success, AuthenticationSuccessListener issues refresh token and sets cookies.
        throw new \LogicException('Intercepted by the JWT firewall.');
    }
}
