<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Entity\User;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\CancelTaskDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskTransition;
use App\Task\Resource\TaskResource;
use App\Task\Security\OwnedTaskValueResolver;
use App\Task\Service\TransitionTask;
use App\Task\ValueObject\CancellationReason;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/v1/tasks/{id}', name: 'api_task_', requirements: ['id' => Requirement::UUID_V7], methods: ['POST'], format: 'json')]
#[OA\Tag(name: 'Task lifecycle')]
#[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Response(response: 200, description: 'The task in its new status', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
#[OA\Response(response: 404, description: 'Task not found')]
#[OA\Response(response: 409, description: 'The task cannot make this transition from its current status')]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
final class TaskTransitionController extends AbstractController
{
    public function __construct(
        private readonly TransitionTask $transitionTask,
    ) {
    }

    #[Route('/start', name: 'start')]
    #[OA\Post(summary: 'Start a task', description: '`todo` → `in_progress`')]
    public function start(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($this->transitionTask->handle($task, TaskTransition::START, $this->user())));
    }

    #[Route('/submit-for-review', name: 'submit_for_review')]
    #[OA\Post(summary: 'Submit a task for review', description: '`in_progress` → `in_review`')]
    public function submitForReview(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($this->transitionTask->handle($task, TaskTransition::SUBMIT_FOR_REVIEW, $this->user())));
    }

    #[Route('/complete', name: 'complete')]
    #[OA\Post(summary: 'Complete a task', description: '`in_review` → `completed`. A completed task is final.')]
    public function complete(#[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($this->transitionTask->handle($task, TaskTransition::COMPLETE, $this->user())));
    }

    #[Route('/cancel', name: 'cancel')]
    #[OA\Post(summary: 'Cancel a task', description: '`todo`, `in_progress` or `in_review` → `cancelled`. A cancelled task is final.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CancelTaskDTO::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function cancel(#[MapRequestPayload(acceptFormat: 'json')] CancelTaskDTO $dto, #[ValueResolver(OwnedTaskValueResolver::class)] Task $task): JsonResponse
    {
        return JsonApiResponse::one(TaskResource::toItem($this->transitionTask->handle($task, TaskTransition::CANCEL, $this->user(), new CancellationReason($dto->reason))));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
