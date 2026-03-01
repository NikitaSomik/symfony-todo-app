<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Entity\User;
use App\Task\DTO\CreateTaskDTO;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/tasks', name: 'api_task_')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository,
        private readonly CreateTask $createTask,
    ) {
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[OA\Get(summary: 'Get all tasks')]
    #[OA\Response(
        response: 200,
        description: 'List of tasks',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: TaskResource::class)))
    )]
    public function getAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tasks = $this->taskRepository->findBy(criteria: ['user' => $user], orderBy: [Task::FIELD_CREATED_AT => 'DESC']);

        return $this->json(data: TaskResource::fromCollection($tasks), status: Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a task')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateTaskDTO::class)))]
    #[OA\Response(response: 201, description: 'Task created', content: new OA\JsonContent(ref: new Model(type: TaskResource::class)))]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateTaskDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $this->createTask->handle($dto, $user);

        return $this->json(data: TaskResource::fromEntity($task), status: Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Get a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Task details', content: new OA\JsonContent(ref: new Model(type: TaskResource::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function get(Task $task): JsonResponse
    {
        return $this->json(data: TaskResource::fromEntity($task), status: Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(summary: 'Update a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UpdateTaskDTO::class)))]
    #[OA\Response(response: 200, description: 'Task updated', content: new OA\JsonContent(ref: new Model(type: TaskResource::class)))]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function update(#[MapRequestPayload] UpdateTaskDTO $dto, Task $task): JsonResponse
    {
        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->setStatus(TaskStatus::from($dto->status));

        $this->em->flush();

        return $this->json(data: TaskResource::fromEntity($task), status: Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete a task')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 404, description: 'Task not found')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[IsGranted(TaskVoter::ACCESS, 'task')]
    public function delete(Task $task): JsonResponse
    {
        $this->em->remove($task);
        $this->em->flush();

        return $this->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
