<?php

declare(strict_types=1);

namespace App\Notification\Listener\Task;

use App\Auth\Contract\UserDirectory;
use App\Task\Contract\TaskAssigned;
use App\Task\Contract\TaskDirectory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Tells someone by email that a task was assigned to them. Runs in the worker, some time after
 * the assignment, so it looks at the task as it is now.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class EmailNewAssignee
{
    public function __construct(
        private TaskDirectory $tasks,
        private UserDirectory $users,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(TaskAssigned $event): void
    {
        // Nobody needs to be told what they did themselves.
        if ($event->assigneeId === $event->actorId) {
            return;
        }

        // The task may have been deleted or handed to someone else since. Whoever left the
        // workspace or became a viewer was taken off it too, so this also keeps a task's title
        // from reaching someone who may no longer see it.
        $task = $this->tasks->findSummary($event->taskId);
        if (null === $task || $task->assigneeId !== $event->assigneeId) {
            return;
        }

        $recipient = $this->users->findEmailById($event->assigneeId);
        if (null === $recipient) {
            return;
        }

        $actor = $this->users->findEmailById($event->actorId) ?? 'Someone';

        $this->mailer->send((new Email())
            ->to($recipient)
            ->subject(\sprintf('You were assigned "%s"', $task->title))
            ->text(\sprintf("%s assigned you a task:\n\n%s\n", $actor, $task->title)));
    }
}
