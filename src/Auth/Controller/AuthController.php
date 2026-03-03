<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\DTO\RegisterDTO;
use App\Auth\Exception\EmailAlreadyTakenException;
use App\Auth\Resource\UserResource;
use App\Auth\Service\RegisterUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly RegisterUser $registerUser,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(summary: 'Register a new user')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: RegisterDTO::class)))]
    #[OA\Response(response: 201, description: 'User registered', content: new OA\JsonContent(ref: new Model(type: UserResource::class)))]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 409, description: 'Email already taken')]
    public function register(#[MapRequestPayload] RegisterDTO $dto): JsonResponse
    {
        try {
            $user = $this->registerUser->handle($dto);
        } catch (EmailAlreadyTakenException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(data: UserResource::fromEntity($user), status: Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(summary: 'Login and receive JWT token')]
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
        response: 200,
        description: 'JWT token',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'token', type: 'string')]
        )
    )]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    public function login(): never
    {
        throw new \LogicException('Intercepted by the JWT firewall.');
    }
}
