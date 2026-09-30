<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\DTO\CreateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Event\TaskCreated;
use App\Task\Identity\TaskIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskIdGenerator $taskIdGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CreateTaskDTO $dto, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($dto, $user): Task {
            $status = $dto->status();

            $task = new Task($this->taskIdGenerator->generate());
            $task->setTitle($dto->title);
            $task->setDescription($dto->description);
            $task->changeStatus($status);

            if (TaskStatus::CANCELLED === $status && null !== $dto->cancellation_reason) {
                $task->setCancellationReason($dto->cancellation_reason);
            }
            $task->setDueDate($dto->dueDate());
            $task->setUser($user);

            $this->em->persist($task);

            $this->eventDispatcher->dispatch(TaskCreated::from(task: $task, actor: $user));

            return $task;
        });
    }
}
