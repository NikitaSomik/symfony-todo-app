<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Api\Documentation\UserResponseSchema;
use App\Auth\Entity\User;
use App\Auth\Resource\UserResource;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
final class ProfileController extends AbstractController
{
    #[Route('/api/v1/profile', name: 'api_profile_me', methods: ['GET'], format: 'json')]
    #[OA\Get(summary: 'Get current authenticated user')]
    #[OA\Response(response: 200, description: 'Current user', content: new JsonApiContent(ref: new Model(type: UserResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return JsonApiResponse::one(UserResource::toItem($user));
    }
}
