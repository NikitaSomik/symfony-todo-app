<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Repository\TaskRepository;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * A rule that the task cannot check from its own fields, contributed from outside the lifecycle:
 * the owner may have only so many tasks in progress. Off unless a test sets a limit.
 */
final class WipLimitGuard
{
    public const string CODE = 'wip_limit_reached';

    public ?int $limit = null;

    public function __construct(
        private readonly TaskRepository $tasks,
    ) {
    }

    /** @param GuardEvent<Task> $event */
    #[AsGuardListener('task_lifecycle', 'start')]
    public function __invoke(GuardEvent $event): void
    {
        if (null === $this->limit) {
            return;
        }

        $inProgress = $this->tasks->count(['user' => $event->getSubject()->getUser(), 'status' => TaskStatus::IN_PROGRESS]);

        if ($inProgress >= $this->limit) {
            $event->addTransitionBlocker(new TransitionBlocker(
                sprintf('No more than %d tasks can be in progress at once.', $this->limit),
                self::CODE,
            ));
        }
    }
}
