<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\Entity\Task;
use App\Task\Event\TaskDeleted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DeleteTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Task $task, int $actorId): void
    {
        $this->em->wrapInTransaction(function () use ($task, $actorId): void {
            $this->eventDispatcher->dispatch(TaskDeleted::from(task: $task, actorId: $actorId));
            $this->em->remove($task);
        });
    }
}
