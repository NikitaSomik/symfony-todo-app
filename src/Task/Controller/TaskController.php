<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Entity\User;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\PaginatedCollection;
use App\Shared\Api\PaginationLinksBuilder;
use App\Shared\Api\ResourceCollection;
use App\Shared\AuditLog\Api\Documentation\AuditLogCollectionResponseSchema;
use App\Shared\AuditLog\Enum\AuditLogEntityType;
use App\Shared\AuditLog\Repository\AuditLogRepository;
use App\Shared\AuditLog\Resource\AuditLogResource;
use App\Shared\Http\PageQueryDTO;
use App\Task\Api\Documentation\TaskCollectionResponseSchema;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\CreateTaskDTO;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\DTO\UpdateTaskDetailsDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskSortField;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use App\Task\Resource\TaskResource;
use App\Task\Security\OwnedTaskValueResolver;
use App\Task\Service\CreateTask;
use App\Task\Service\DeleteTask;
use App\Task\Service\UpdateTaskDetails;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/v1/tasks', name: 'api_task_', format: 'json')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly CreateTask $createTask,
        private readonly UpdateTaskDetails $updateTaskDetails,
        private readonly DeleteTask $deleteTask,
        private readonly PaginationLinksBuilder $paginationLinksBuilder,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get all tasks (paginated)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'number', type: 'integer', default: 1, maximum: PageQueryDTO::MAX_NUMBER, minimum: 1, example: 1), new OA\Property(property: 'size', type: 'integer', default: 20, maximum: 100, minimum: 1, example: 20)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'filter', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), nullable: true), new OA\Property(property: 'due_from', type: 'string', format: 'date', example: '2026-04-01', nullable: true), new OA\Property(property: 'due_to', type: 'string', format: 'date', example: '2026-04-30', nullable: true), new OA\Property(property: 'search', description: 'Full-text search across task title and description. Without `sort`, results are ranked by relevance.', type: 'string', maxLength: 100, example: 'milk', nullable: true)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'sort', in: 'query', description: 'Defaults to relevance when `filter[search]` is given, otherwise to `created_at`. With a search, tasks that tie on the chosen field are ranked by relevance.', schema: new OA\Schema(type: 'string', enum: [TaskSortField::CREATED_AT->value, TaskSortField::STATUS->value, TaskSortField::DUE_DATE->value]))]
    #[OA\Parameter(name: 'direction', in: 'query', description: 'Applies to the chosen sort, or to relevance when a search has no `sort`.', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc']))]
    #[OA\Response(response: 200, description: 'Paginated list of tasks', content: new JsonApiContent(ref: new Model(type: TaskCollectionResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function getAll(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        TaskListQueryDTO $query,
        Request $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $tasks = $this->taskRepository->findForUserList($user, $query);
        $total = $this->taskRepository->countForUserList($user, $query);

        return JsonApiResponse::collection(
            new PaginatedCollection(
                items: TaskResource::toItems($tasks),
                pageNumber: $query->page->number,
                pageSize: $query->page->size,
                total: $total,
                links: $this->paginationLinksBuilder->build($request, $query->page->number, $query->page->size, $total),
            ),
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a task', description: 'A new task always starts in `todo`.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateTaskDTO::class)))]
    #[OA\Response(response: 201, description: 'Task created', headers: [new OA\Header(header: 'Location', description: 'URL of the created task', schema: new OA\Schema(type: 'string'))], content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateTaskDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $this->createTask->handle($dto, $user);

        return JsonApiResponse::created(
            TaskResource::toItem($task),
            $this->generateUrl('api_task_get', ['id' => $task->getId()->toRfc4122()]),
        );
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(summary: 'Get a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Task details', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function get(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($task));
    }

    #[Route('/{id}/audit-logs', name: 'get_audit_logs', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(summary: 'Get task audit log history')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Task audit log history', content: new JsonApiContent(ref: new Model(type: AuditLogCollectionResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function getAuditLogs(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
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
    #[OA\Put(summary: 'Update the title, description and due date of a task', description: 'The status is not part of the body: a task moves through its lifecycle with the transition endpoints.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UpdateTaskDetailsDTO::class)))]
    #[OA\Response(response: 200, description: 'Task updated', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function update(#[MapRequestPayload(acceptFormat: 'json')] UpdateTaskDetailsDTO $dto, #[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $this->updateTaskDetails->handle($task, $dto, $user);

        return JsonApiResponse::one(TaskResource::toItem($task));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function delete(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->deleteTask->handle($task, $user);

        return JsonApiResponse::noContent();
    }
}
