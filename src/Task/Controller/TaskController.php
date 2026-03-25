<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Entity\User;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\PaginatedCollection;
use App\Shared\Api\PaginationLinksBuilder;
use App\Task\Api\Documentation\TaskCollectionResponseSchema;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\CreateTaskDTO;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\DTO\UpdateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use App\Task\Resource\TaskResource;
use App\Task\Security\TaskVoter;
use App\Task\Service\CreateTask;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/tasks', name: 'api_task_', format: 'json')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository,
        private readonly CreateTask $createTask,
        private readonly PaginationLinksBuilder $paginationLinksBuilder,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get all tasks (paginated)')]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'number', type: 'integer', default: 1, minimum: 1, example: 1), new OA\Property(property: 'size', type: 'integer', default: 20, maximum: 100, minimum: 1, example: 20)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'filter', in: 'query', schema: new OA\Schema(properties: [new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), nullable: true)], type: 'object'), style: 'deepObject', explode: true, )]
    #[OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'title', 'status']))]
    #[OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc']))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 100, example: 'milk', nullable: true))]
    #[OA\Response(response: 200, description: 'Paginated list of tasks', content: new OA\JsonContent(ref: new Model(type: TaskCollectionResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function getAll(#[MapQueryString] TaskListQueryDTO $query, Request $request): JsonResponse
    {
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
    #[OA\Post(summary: 'Create a task')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateTaskDTO::class)))]
    #[OA\Response(response: 201, description: 'Task created', content: new OA\JsonContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    public function create(#[MapRequestPayload] CreateTaskDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $this->createTask->handle($dto, $user);

        return JsonApiResponse::one(TaskResource::toItem($task), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Get a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Task details', content: new OA\JsonContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function get(Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($task));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(summary: 'Update a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UpdateTaskDTO::class)))]
    #[OA\Response(response: 200, description: 'Task updated', content: new OA\JsonContent(ref: new Model(type: TaskResponseSchema::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function update(#[MapRequestPayload] UpdateTaskDTO $dto, Task $task): JsonResponse
    {
        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->setStatus(TaskStatus::from($dto->status));

        $this->em->flush();

        return JsonApiResponse::one(TaskResource::toItem($task));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function delete(Task $task): Response
    {
        $this->em->remove($task);
        $this->em->flush();

        return JsonApiResponse::empty();
    }
}
