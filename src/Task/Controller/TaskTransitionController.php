<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Task\Api\Documentation\TaskResponseSchema;
use App\Task\DTO\BlockTaskDTO;
use App\Task\DTO\CancelTaskDTO;
use App\Task\Entity\Task;
use App\Task\Resource\TaskResource;
use App\Task\Security\MemberTaskValueResolver;
use App\Task\Security\TaskVoter;
use App\Task\Service\BlockTask;
use App\Task\Service\CancelTask;
use App\Task\Service\CompleteTask;
use App\Task\Service\StartTask;
use App\Task\Service\SubmitTaskForReview;
use App\Task\Service\UnblockTask;
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

#[Route('/api/v1/tasks/{id}', name: 'api_task_', requirements: ['id' => Requirement::UUID_V7], methods: ['POST'], format: 'json')]
#[IsGranted(TaskVoter::WRITE, subject: 'task')]
#[OA\Tag(name: 'Task lifecycle')]
#[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Response(response: 200, description: 'The task in its new status', content: new JsonApiContent(ref: new Model(type: TaskResponseSchema::class)))]
#[OA\Response(response: 403, description: 'The user is a viewer in the workspace of the task')]
#[OA\Response(response: 404, description: 'Task not found, or the user is not a member of its workspace')]
#[OA\Response(response: 409, description: 'The task cannot make this transition from its current status')]
#[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
final class TaskTransitionController extends AbstractController
{
    public function __construct(
        private readonly TaskResource $taskResource,
        private readonly StartTask $startTask,
        private readonly SubmitTaskForReview $submitTaskForReview,
        private readonly CompleteTask $completeTask,
        private readonly CancelTask $cancelTask,
        private readonly BlockTask $blockTask,
        private readonly UnblockTask $unblockTask,
    ) {
    }

    #[Route('/start', name: 'start')]
    #[OA\Post(summary: 'Start a task', description: '`todo` → `in_progress`')]
    public function start(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->startTask->handle($task, $user->id())));
    }

    #[Route('/submit-for-review', name: 'submit_for_review')]
    #[OA\Post(summary: 'Submit a task for review', description: '`in_progress` → `in_review`')]
    public function submitForReview(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->submitTaskForReview->handle($task, $user->id())));
    }

    #[Route('/complete', name: 'complete')]
    #[OA\Post(summary: 'Complete a task', description: '`in_review` → `completed`. A completed task is final.')]
    public function complete(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->completeTask->handle($task, $user->id())));
    }

    #[Route('/block', name: 'block')]
    #[OA\Post(summary: 'Block a task', description: '`in_progress` → `blocked`. The reason stays with the task until it is unblocked.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: BlockTaskDTO::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function block(#[MapRequestPayload(acceptFormat: 'json')] BlockTaskDTO $dto, #[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->blockTask->handle($task, $dto->reason(), $user->id())));
    }

    #[Route('/unblock', name: 'unblock')]
    #[OA\Post(summary: 'Unblock a task', description: '`blocked` → `in_progress`')]
    public function unblock(#[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->unblockTask->handle($task, $user->id())));
    }

    #[Route('/cancel', name: 'cancel')]
    #[OA\Post(summary: 'Cancel a task', description: '`todo`, `in_progress`, `blocked` or `in_review` → `cancelled`. A cancelled task is final.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CancelTaskDTO::class)))]
    #[OA\Response(response: 415, description: 'Body is not sent as application/json')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function cancel(#[MapRequestPayload(acceptFormat: 'json')] CancelTaskDTO $dto, #[ValueResolver(MemberTaskValueResolver::class)] Task $task, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return JsonApiResponse::one($this->taskResource->toItem($this->cancelTask->handle($task, $dto->reason(), $user->id())));
    }
}
