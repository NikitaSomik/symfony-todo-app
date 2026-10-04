<?php

declare(strict_types=1);

namespace App\Workspace\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceCollection;
use App\Workspace\Api\Documentation\WorkspaceCollectionResponseSchema;
use App\Workspace\Api\Documentation\WorkspaceResponseSchema;
use App\Workspace\DTO\WorkspaceDTO;
use App\Workspace\Entity\Workspace;
use App\Workspace\Repository\WorkspaceRepository;
use App\Workspace\Resource\WorkspaceResource;
use App\Workspace\Security\MemberWorkspaceValueResolver;
use App\Workspace\Security\WorkspaceVoter;
use App\Workspace\Service\CreateWorkspace;
use App\Workspace\Service\RenameWorkspace;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/workspaces', name: 'api_workspace_', format: 'json')]
#[OA\Tag(name: 'Workspaces')]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
final class WorkspaceController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly WorkspaceResource $workspaceResource,
        private readonly CreateWorkspace $createWorkspace,
        private readonly RenameWorkspace $renameWorkspace,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get the workspaces the user is a member of')]
    #[OA\Response(response: 200, description: 'Workspaces, oldest first', content: new JsonApiContent(ref: new Model(type: WorkspaceCollectionResponseSchema::class)))]
    public function getAll(#[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::collection(new ResourceCollection(
            items: $this->workspaceResource->toItems($this->workspaces->findForMember($user->id()), $user->id()),
            links: ['self' => $this->generateUrl('api_workspace_get_all')],
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a workspace', description: 'The user who creates it becomes its owner.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: WorkspaceDTO::class)))]
    #[OA\Response(response: 201, description: 'Workspace created', headers: [new OA\Header(header: 'Location', description: 'URL of the created workspace', schema: new OA\Schema(type: 'string'))], content: new JsonApiContent(ref: new Model(type: WorkspaceResponseSchema::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] WorkspaceDTO $dto, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $workspace = $this->createWorkspace->handle(trim($dto->name), $user->id());

        return JsonApiResponse::created(
            $this->workspaceResource->toItem($workspace, $user->id()),
            $this->generateUrl('api_workspace_get', ['id' => $workspace->getId()->toRfc4122()]),
        );
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(summary: 'Get a workspace')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Workspace details', content: new JsonApiContent(ref: new Model(type: WorkspaceResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
    public function get(#[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->workspaceResource->toItem($workspace, $user->id()));
    }

    #[Route('/{id}', name: 'rename', requirements: ['id' => Requirement::UUID_V7], methods: ['PUT'])]
    #[IsGranted(WorkspaceVoter::MANAGE, subject: 'workspace')]
    #[OA\Put(summary: 'Rename a workspace', description: 'Owners only.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: WorkspaceDTO::class)))]
    #[OA\Response(response: 200, description: 'Workspace renamed', content: new JsonApiContent(ref: new Model(type: WorkspaceResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a member but not an owner')]
    #[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function rename(#[MapRequestPayload(acceptFormat: 'json')] WorkspaceDTO $dto, #[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $workspace = $this->renameWorkspace->handle($workspace, trim($dto->name), $user->id());

        return JsonApiResponse::one($this->workspaceResource->toItem($workspace, $user->id()));
    }
}
