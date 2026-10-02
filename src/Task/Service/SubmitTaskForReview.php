<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\AuditLog\TaskState;
use App\Task\Entity\Task;
use App\Task\Event\TaskStatusChanged;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class SubmitTaskForReview
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        #[Target('task_lifecycle')]
        private WorkflowInterface $taskLifecycle,
    ) {
    }

    public function handle(Task $task, User $user): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $user): Task {
            $previousState = TaskState::fromTask($task);

            $this->taskLifecycle->apply($task, 'submit_for_review', ['at' => $this->clock->now()]);
            $this->eventDispatcher->dispatch(TaskStatusChanged::from($task, $user, $previousState));

            return $task;
        });
    }
}
