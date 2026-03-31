<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\DTO\CreateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;

final class CreateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(CreateTaskDTO $dto, User $user): Task
    {
        $task = new Task();
        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->changeStatus(TaskStatus::from($dto->status), $dto->cancellation_reason);
        $task->setDueDate($dto->dueDate());
        $task->setUser($user);

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }
}
