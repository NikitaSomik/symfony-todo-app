<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\AssignTaskDTO;
use App\Task\Entity\Task;
use App\Task\Resource\TaskResource;
use App\Task\Security\MemberTaskValueResolver;
use App\Task\Security\TaskVoter;
use App\Task\Service\AssignTask;
use App\Task\Service\UnassignTask;
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

#[Route('/api/v1/tasks/{id}/assignee', name: 'api_task_', requirements: ['id' => Requirement::UUID_V7], format: 'json')]
#[IsGranted(TaskVoter::WRITE, subject: 'task')]
#[OA\Tag(name: 'Task assignee')]
#[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Response(response: 200, description: 'The task with its assignee', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
#[OA\Response(response: 403, description: 'The user is a viewer in the workspace of the task')]
#[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
#[OA\Response(response: 409, description: 'A completed or cancelled task keeps its assignee')]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
final class TaskAssigneeController extends AbstractController
{
    public function __construct(
        private readonly TaskResource $taskResource,
        private readonly AssignTask $assignTask,
        private readonly UnassignTask $unassignTask,
    ) {
    }

    #[Route('', name: 'assign', methods: ['PUT'])]
    #[OA\Put(summary: 'Assign a task', description: 'To an owner or a member of the workspace of the task; a viewer cannot be an assignee.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AssignTaskDTO::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(response: 422, description: 'Invalid body, or the user cannot work in the workspace of the task')]
    public function assign(#[MapRequestPayload(acceptFormat: 'json')] AssignTaskDTO $dto, #[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->assignTask->handle($task, assigneeId: $dto->user_id, actorId: $user->id())));
    }

    #[Route('', name: 'unassign', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Take a task off its assignee')]
    public function unassign(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->unassignTask->handle($task, $user->id())));
    }
}
