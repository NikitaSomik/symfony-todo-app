<?php

declare(strict_types=1);

namespace App\Workspace\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceCollection;
use App\Workspace\Api\Documentation\MemberCollectionResponseSchema;
use App\Workspace\Api\Documentation\MemberResponseSchema;
use App\Workspace\DTO\AddMemberDTO;
use App\Workspace\DTO\ChangeMemberRoleDTO;
use App\Workspace\Entity\Workspace;
use App\Workspace\Resource\MemberResource;
use App\Workspace\Security\MemberWorkspaceValueResolver;
use App\Workspace\Security\WorkspaceVoter;
use App\Workspace\Service\AddMember;
use App\Workspace\Service\ChangeMemberRole;
use App\Workspace\Service\RemoveMember;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/workspaces/{id}/members', name: 'api_workspace_member_', requirements: ['id' => Requirement::UUID_V7, 'userId' => Requirement::DIGITS], format: 'json')]
#[OA\Tag(name: 'Workspace members')]
#[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
#[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
final class WorkspaceMemberController extends AbstractController
{
    public function __construct(
        private readonly MemberResource $memberResource,
        private readonly AddMember $addMember,
        private readonly ChangeMemberRole $changeMemberRole,
        private readonly RemoveMember $removeMember,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get the members of a workspace')]
    #[OA\Response(response: 200, description: 'Members, in the order they joined', content: new JsonApiContent(ref: new Model(type: MemberCollectionResponseSchema::class)))]
    public function getAll(#[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace): JsonResponse
    {
        return JsonApiResponse::collection(new ResourceCollection(
            items: $this->memberResource->toItems($workspace->getMembers()),
            links: ['self' => $this->generateUrl('api_workspace_member_get_all', ['id' => $workspace->getId()->toRfc4122()])],
        ));
    }

    #[Route('', name: 'add', methods: ['POST'])]
    #[IsGranted(WorkspaceVoter::MANAGE, subject: 'workspace')]
    #[OA\Post(summary: 'Add a registered user to a workspace', description: 'Owners only.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AddMemberDTO::class)))]
    #[OA\Response(response: 201, description: 'Member added', headers: [new OA\Header(header: 'Location', description: 'URL of the new member', schema: new OA\Schema(type: 'string'))], content: new JsonApiContent(ref: new Model(type: MemberResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a member but not an owner')]
    #[OA\Response(response: 409, description: 'The user is already a member')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(response: 422, description: 'Invalid body, or no user is registered with this email')]
    public function add(#[MapRequestPayload(acceptFormat: 'json')] AddMemberDTO $dto, #[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $membership = $this->addMember->handle($workspace, $dto->email, $dto->role(), $user->id());

        return JsonApiResponse::created($this->memberResource->toItem($membership), $this->memberResource->selfUrl($membership));
    }

    #[Route('/{userId}', name: 'get', methods: ['GET'])]
    #[OA\Get(summary: 'Get a member of a workspace')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'The member', content: new JsonApiContent(ref: new Model(type: MemberResponseSchema::class)))]
    public function get(int $userId, #[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace): JsonResponse
    {
        return JsonApiResponse::one($this->memberResource->toItem($workspace->getMember($userId)));
    }

    #[Route('/{userId}', name: 'change_role', methods: ['PUT'])]
    #[IsGranted(WorkspaceVoter::MANAGE, subject: 'workspace')]
    #[OA\Put(summary: 'Change the role of a member', description: 'Owners only. A workspace keeps at least one owner.')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ChangeMemberRoleDTO::class)))]
    #[OA\Response(response: 200, description: 'Role changed', content: new JsonApiContent(ref: new Model(type: MemberResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a member but not an owner')]
    #[OA\Response(response: 409, description: 'The last owner cannot be given another role')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function changeRole(int $userId, #[MapRequestPayload(acceptFormat: 'json')] ChangeMemberRoleDTO $dto, #[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $membership = $this->changeMemberRole->handle($workspace, $userId, $dto->role(), $user->id());

        return JsonApiResponse::one($this->memberResource->toItem($membership));
    }

    #[Route('/{userId}', name: 'remove', methods: ['DELETE'])]
    #[IsGranted(WorkspaceVoter::MANAGE, subject: 'workspace')]
    #[OA\Delete(summary: 'Remove a member', description: 'Owners only, and only someone else: an owner who wants out leaves the workspace.')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Member removed')]
    #[OA\Response(response: 403, description: 'The user is a member but not an owner')]
    #[OA\Response(response: 409, description: 'An owner cannot remove themselves')]
    public function remove(int $userId, #[ValueResolver(MemberWorkspaceValueResolver::class)] Workspace $workspace, #[CurrentUser] AuthenticatedUser $user): Response
    {
        $this->removeMember->handle($workspace, $userId, $user->id());

        return JsonApiResponse::noContent();
    }
}
