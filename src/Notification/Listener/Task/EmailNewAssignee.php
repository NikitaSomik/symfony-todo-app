<?php

declare(strict_types=1);

namespace App\Notification\Listener\Task;

use App\Auth\Contract\UserDirectory;
use App\Task\Contract\TaskAssigned;
use App\Task\Contract\TaskDirectory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

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
        if ($event->assigneeId === $event->actorId) {
            return;
        }

        // The worker runs later: the task may be gone or handed on by now. Whoever lost access
        // was taken off their tasks too, so this also keeps the title from them.
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
