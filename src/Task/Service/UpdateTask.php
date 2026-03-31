<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\DTO\UpdateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use App\Task\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class UpdateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function handle(Task $task, UpdateTaskDTO $dto): Task
    {
        $previousStatus = $task->getStatus();
        $nextStatus = TaskStatus::from($dto->status);

        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->changeStatus($nextStatus);

        if (TaskStatus::CANCELLED === $nextStatus && null !== $dto->cancellation_reason) {
            $task->setCancellationReason($dto->cancellation_reason);
        }

        $task->setDueDate($dto->dueDate());

        if ($previousStatus !== $nextStatus) {
            $this->em->persist(new TaskStatusChange($task, $previousStatus, $nextStatus, $this->clock->now()));
        }

        $this->em->flush();

        return $task;
    }
}
