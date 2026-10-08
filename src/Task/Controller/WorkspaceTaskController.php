<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\PaginatedCollection;
use App\Shared\Api\PaginationLinksBuilder;
use App\Shared\Api\ResourceCollection;
use App\Shared\Http\PageQueryDTO;
use App\Task\Api\Documentation\TaskCollectionResponseSchema;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\CreateTaskDTO;
use App\Task\DTO\ReassignTasksDTO;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Enum\TaskSortField;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use App\Task\Resource\TaskResource;
use App\Task\Security\TaskVoter;
use App\Task\Security\WorkspaceContext;
use App\Task\Security\WorkspaceContextValueResolver;
use App\Task\Service\CreateTask;
use App\Task\Service\ReassignTasks;
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
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/workspaces/{id}/tasks', name: 'api_workspace_task_', requirements: ['id' => Requirement::UUID_V7], format: 'json')]
#[OA\Tag(name: 'Tasks')]
#[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
#[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
final class WorkspaceTaskController extends AbstractController
{
    public function __construct(
        private readonly TaskResource $taskResource,
        private readonly TaskRepository $taskRepository,
        private readonly CreateTask $createTask,
        private readonly ReassignTasks $reassignTasks,
        private readonly PaginationLinksBuilder $paginationLinksBuilder,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get the tasks of a workspace (paginated)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'number', type: 'integer', default: 1, maximum: PageQueryDTO::MAX_NUMBER, minimum: 1, example: 1), new OA\Property(property: 'size', type: 'integer', default: 20, maximum: 100, minimum: 1, example: 20)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'filter', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), nullable: true), new OA\Property(property: 'due_from', type: 'string', format: 'date', example: '2026-04-01', nullable: true), new OA\Property(property: 'due_to', type: 'string', format: 'date', example: '2026-04-30', nullable: true), new OA\Property(property: 'search', description: 'Full-text search across task title and description. Without `sort`, results are ranked by relevance.', type: 'string', maxLength: 100, example: 'milk', nullable: true)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'sort', in: 'query', description: 'Defaults to relevance when `filter[search]` is given, otherwise to `created_at`. With a search, tasks that tie on the chosen field are ranked by relevance.', schema: new OA\Schema(type: 'string', enum: [TaskSortField::CREATED_AT->value, TaskSortField::STATUS->value, TaskSortField::DUE_DATE->value]))]
    #[OA\Parameter(name: 'direction', in: 'query', description: 'Applies to the chosen sort, or to relevance when a search has no `sort`.', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc']))]
    #[OA\Response(response: 200, description: 'Paginated list of tasks', content: new JsonApiContent(ref: new Model(type: TaskCollectionResponseSchema::class)))]
    public function getAll(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        TaskListQueryDTO $query,
        Request $request,
        #[ValueResolver(WorkspaceContextValueResolver::class)]
        WorkspaceContext $context,
    ): JsonResponse {
        $tasks = $this->taskRepository->findForWorkspaceList($context->workspaceId, $query);
        $total = $this->taskRepository->countForWorkspaceList($context->workspaceId, $query);

        return JsonApiResponse::collection(
            new PaginatedCollection(
                items: $this->taskResource->toItems($tasks),
                pageNumber: $query->page->number,
                pageSize: $query->page->size,
                total: $total,
                links: $this->paginationLinksBuilder->build($request, $query->page->number, $query->page->size, $total),
            ),
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted(TaskVoter::WRITE, subject: 'context')]
    #[OA\Post(summary: 'Create a task in a workspace', description: 'A new task always starts in `todo`. Owners and members only.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateTaskDTO::class)))]
    #[OA\Response(response: 201, description: 'Task created', headers: [new OA\Header(header: 'Location', description: 'URL of the created task', schema: new OA\Schema(type: 'string'))], content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a viewer in the workspace')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateTaskDTO $dto,
        #[ValueResolver(WorkspaceContextValueResolver::class)]
        WorkspaceContext $context,
        #[CurrentUser]
        AuthenticatedUser $user,
    ): JsonResponse {
        $task = $this->createTask->handle($context->workspaceId, $dto->details(), $user->id());

        return JsonApiResponse::created(
            $this->taskResource->toItem($task),
            $this->generateUrl('api_task_get', ['id' => $task->getId()->toRfc4122()]),
        );
    }

    #[Route('/reassign', name: 'reassign', methods: ['POST'])]
    #[IsGranted(TaskVoter::WRITE, subject: 'context')]
    #[OA\Post(summary: 'Hand the unfinished tasks of one user over to another', description: 'Owners and members only. Completed and cancelled tasks keep their assignee.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ReassignTasksDTO::class)))]
    #[OA\Response(response: 200, description: 'The tasks that changed hands', content: new JsonApiContent(ref: new Model(type: TaskCollectionResponseSchema::class)))]
    #[OA\Response(response: 403, description: 'The user is a viewer in the workspace')]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(response: 422, description: 'Invalid body, or the user named in `to` cannot work in the workspace')]
    public function reassign(
        #[MapRequestPayload(acceptFormat: 'json')]
        ReassignTasksDTO $dto,
        #[ValueResolver(WorkspaceContextValueResolver::class)]
        WorkspaceContext $context,
        #[CurrentUser]
        AuthenticatedUser $user,
    ): JsonResponse {
        $tasks = $this->reassignTasks->handle($context->workspaceId, fromUserId: $dto->from, toUserId: $dto->to, actorId: $user->id());

        return JsonApiResponse::collection(new ResourceCollection(items: $this->taskResource->toItems($tasks)));
    }
}
