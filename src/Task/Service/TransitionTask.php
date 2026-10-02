<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Auth\Entity\User;
use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Enum\TaskTransition;
use App\Task\Exception\TaskTransitionNotAllowedException;
use App\Task\ValueObject\CancellationReason;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class TransitionTask
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Target('task_lifecycle')]
        private WorkflowInterface $taskLifecycle,
    ) {
    }

    public function handle(Task $task, TaskTransition $transition, User $user, ?CancellationReason $reason = null): Task
    {
        return $this->em->wrapInTransaction(function () use ($task, $transition, $user, $reason): Task {
            try {
                $this->taskLifecycle->apply($task, $transition->value, [
                    'actor' => $user,
                    'at' => $this->clock->now(),
                    'reason' => $reason,
                ]);
            } catch (NotEnabledTransitionException $exception) {
                throw $this->refusal($task, $transition, $exception);
            }

            return $task;
        });
    }

    /**
     * The workflow's exception is not written for clients. A guard's own message is; a transition
     * the current status does not allow gets the two statuses named.
     */
    private function refusal(Task $task, TaskTransition $transition, NotEnabledTransitionException $exception): TaskTransitionNotAllowedException
    {
        foreach ($exception->getTransitionBlockerList() as $blocker) {
            if (TransitionBlocker::BLOCKED_BY_MARKING !== $blocker->getCode()) {
                return TaskTransitionNotAllowedException::because($blocker->getMessage());
            }
        }

        foreach ($this->taskLifecycle->getDefinition()->getTransitions() as $candidate) {
            if ($candidate->getName() === $transition->value) {
                return TaskTransitionNotAllowedException::between($task->getStatus(), TaskStatus::from($candidate->getTos()[0]));
            }
        }

        throw $exception;
    }
}
