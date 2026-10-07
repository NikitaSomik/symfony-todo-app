<?php

declare(strict_types=1);

namespace App\Task\Resource;

use App\Shared\Api\ResourceItem;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Enum\TaskTransition;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'tasks'),
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b'),
        new OA\Property(
            property: 'attributes',
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
                new OA\Property(property: 'description', type: 'string', example: '2 liters', nullable: true),
                new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), description: 'Task status'),
                new OA\Property(property: 'cancellation_reason', type: 'string', example: 'Task is no longer needed', nullable: true),
                new OA\Property(property: 'block_reason', description: 'Set while the task is blocked', type: 'string', example: 'Waiting for access from the client', nullable: true),
                new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'relationships',
            properties: [
                new OA\Property(
                    property: 'workspace',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: 'workspaces'), new OA\Property(property: 'id', type: 'string', format: 'uuid')], type: 'object')],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'creator',
                    properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'type', type: 'string', example: 'users'), new OA\Property(property: 'id', type: 'string', example: '7')], type: 'object')],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'links',
            description: 'The task itself and the transitions its current status allows; a transition that is not allowed has no link',
            properties: [
                new OA\Property(property: 'self', type: 'string', example: '/api/v1/tasks/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b'),
                new OA\Property(property: 'start', type: 'string', example: '/api/v1/tasks/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b/start'),
                new OA\Property(property: 'submit_for_review', type: 'string'),
                new OA\Property(property: 'complete', type: 'string'),
                new OA\Property(property: 'block', type: 'string'),
                new OA\Property(property: 'unblock', type: 'string'),
                new OA\Property(property: 'cancel', type: 'string', example: '/api/v1/tasks/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b/cancel'),
            ],
            type: 'object',
        ),
    ]
)]
final readonly class TaskResource
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function toItem(Task $task): ResourceItem
    {
        return new ResourceItem(
            type: 'tasks',
            id: $task->getId()->toRfc4122(),
            attributes: [
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus()->value,
                'cancellation_reason' => $task->getCancellationReason(),
                'block_reason' => $task->getBlockReason(),
                'due_date' => $task->getDueDate()?->format('Y-m-d'),
                'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ],
            relationships: [
                'workspace' => ['data' => ['type' => 'workspaces', 'id' => $task->getWorkspaceId()->toRfc4122()]],
                'creator' => ['data' => ['type' => 'users', 'id' => (string) $task->getCreatorId()]],
            ],
            links: $this->links($task),
        );
    }

    /** @return array<string, string> */
    private function links(Task $task): array
    {
        $id = ['id' => $task->getId()->toRfc4122()];
        $links = ['self' => $this->urls->generate('api_task_get', $id)];

        foreach (TaskTransition::availableFrom($task->getStatus()) as $transition) {
            $links[$transition->value] = $this->urls->generate('api_task_'.$transition->value, $id);
        }

        return $links;
    }

    /**
     * @param Task[] $tasks
     *
     * @return ResourceItem[]
     */
    public function toItems(array $tasks): array
    {
        return array_map($this->toItem(...), $tasks);
    }
}
