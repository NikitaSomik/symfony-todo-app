<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
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

    public function handle(Task $task, User $user): void
    {
        $this->em->wrapInTransaction(function () use ($task, $user): void {
            $this->eventDispatcher->dispatch(TaskDeleted::from(task: $task, actor: $user));
            $this->em->remove($task);
        });
    }
}
