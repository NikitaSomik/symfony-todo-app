<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\DTO\CreateTaskDTO;
use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;

final class CreateTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(CreateTaskDTO $dto): Task
    {
        $task = new Task($dto->title);
        $task->setDescription($dto->description);
        $task->setStatus(TaskStatus::from($dto->status));

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }
}
