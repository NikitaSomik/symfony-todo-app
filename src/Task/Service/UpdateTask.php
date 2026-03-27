<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\DTO\UpdateTaskDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(Task $task, UpdateTaskDTO $dto): Task
    {
        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->setStatus(TaskStatus::from($dto->status));

        $this->em->flush();

        return $task;
    }
}
