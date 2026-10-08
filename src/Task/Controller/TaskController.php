<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\AuditLog\Api\Documentation\AuditLogCollectionResponseSchema;
use App\AuditLog\Enum\AuditLogEntityType;
use App\AuditLog\Repository\AuditLogRepository;
use App\AuditLog\Resource\AuditLogResource;
use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceCollection;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\UpdateTaskDetailsDTO;
use App\Task\Entity\Task;
use App\Task\Resource\TaskResource;
use App\Task\Security\MemberTaskValueResolver;
use App\Task\Security\TaskVoter;
use App\Task\Service\DeleteTask;
use App\Task\Service\UpdateTaskDetails;
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

#[Route('/api/v1/tasks', name: 'api_task_', format: 'json')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskResource $taskResource,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly UpdateTaskDetails $updateTaskDetails,
        private readonly DeleteTask $deleteTask,
    ) {
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(summary: 'Get a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Task details', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function get(#[ValueResolver(MemberTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($task));
    }

    #[Route('/{id}/audit-logs', name: 'get_audit_logs', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(summary: 'Get task audit log history')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Task audit log history', content: new JsonApiContent(ref: new Model(type: AuditLogCollectionResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function getAuditLogs(#[ValueResolver(MemberTaskValueResolver::class)] Task $task): JsonResponse
    {
        $taskId = $task->getId()->toRfc4122();

        $auditLogs = $this->auditLogRepository->findForEntity(AuditLogEntityType::TASK, $taskId);
        $auditLogUrl = $this->generateUrl('api_task_get_audit_logs', ['id' => $taskId]);

        return JsonApiResponse::collection(
            new ResourceCollection(
                items: AuditLogResource::toItems($auditLogs),
                links: [
                    'self' => $auditLogUrl,
                ],
            ),
        );
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID_V7], methods: ['PUT'])]
    #[IsGranted(TaskVoter::WRITE, subject: 'task')]
    #[OA\Put(summary: 'Update the title, description and due date of a task', description: 'The status is not part of the body: a task moves through its lifecycle with the transition endpoints.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UpdateTaskDetailsDTO::class)))]
    #[OA\Response(response: 200, description: 'Task updated', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a viewer in the workspace of the task')]
    #[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function update(#[MapRequestPayload(acceptFormat: 'json')] UpdateTaskDetailsDTO $dto, #[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $task = $this->updateTaskDetails->handle($task, $dto->details(), $user->id());

        return JsonApiResponse::one($this->taskResource->toItem($task));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[IsGranted(TaskVoter::DELETE, subject: 'task')]
    #[OA\Delete(summary: 'Delete a task', description: 'Owners only. A task that is no longer needed is cancelled, with a reason; deleting is for a task created by mistake.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 403, description: 'The user is not an owner of the workspace of the task')]
    #[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function delete(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): Response
    {
        $this->deleteTask->handle($task, $user->id());

        return JsonApiResponse::noContent();
    }
}
