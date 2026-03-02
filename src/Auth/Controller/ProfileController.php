<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Resource\UserResource;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/profile', name: 'api_profile_')]
#[OA\Tag(name: 'Profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'me', methods: ['GET'])]
    #[OA\Get(summary: 'Get current authenticated user')]
    #[OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(ref: new Model(type: UserResource::class)))]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(data: UserResource::fromEntity($user), status: Response::HTTP_OK);
    }
}
