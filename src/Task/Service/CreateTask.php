<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\Entity\Task;
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

    public function handle(string $title, ?string $description, ?\DateTimeImmutable $dueDate, int $creatorId): Task
    {
        return $this->em->wrapInTransaction(function () use ($title, $description, $dueDate, $creatorId): Task {
            $task = new Task($this->taskIdGenerator->generate(), $creatorId);
            $task->setTitle($title);
            $task->setDescription($description);
            $task->setDueDate($dueDate);

            $this->em->persist($task);

            $this->eventDispatcher->dispatch(TaskCreated::from(task: $task, actorId: $creatorId));

            return $task;
        });
    }
}
