<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\Entity\Task;
use App\Task\Event\TaskCreated;
use App\Task\Identity\TaskIdGenerator;
use App\Task\ValueObject\TaskDetails;
use App\Workspace\Contract\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskIdGenerator $taskIdGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WorkspaceAccess $workspaces,
    ) {
    }

    public function handle(Uuid $workspaceId, TaskDetails $details, int $creatorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($workspaceId, $details, $creatorId): Task {
            $task = new Task($this->taskIdGenerator->generate(), $this->workspaces->reference($workspaceId), $creatorId);
            $task->setTitle($details->title);
            $task->setDescription($details->description);
            $task->setDueDate($details->dueDate);

            $this->em->persist($task);

            $this->eventDispatcher->dispatch(TaskCreated::from(task: $task, actorId: $creatorId));

            return $task;
        });
    }
}
