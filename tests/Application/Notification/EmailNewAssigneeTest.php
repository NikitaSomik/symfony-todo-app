<?php

declare(strict_types=1);

namespace App\Tests\Application\Notification;

use App\Auth\Entity\User;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Task\Contract\TaskAssigned;
use App\Task\Entity\Task;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class EmailNewAssigneeTest extends ApiTestCase
{
    private User $owner;
    private User $colleague;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = UserFactory::createOne(['email' => 'owner@example.com']);
        $this->colleague = UserFactory::createOne(['email' => 'colleague@example.com']);
        $this->actingAs($this->owner);
        $this->workspace = WorkspaceFactory::new()->withMembers([[$this->colleague, WorkspaceRole::MEMBER]])->create(['owner' => $this->owner]);
    }

    private function task(string $title = 'Prepare the release notes'): Task
    {
        return TaskFactory::createOne(['workspace' => $this->workspace, 'title' => $title]);
    }

    private function assign(Task $task, User $assignee): void
    {
        $this->put($this->route('api_task_assign', ['id' => $task->getId()->toRfc4122()]), ['user_id' => $assignee->id()]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Read it before the next request: that one boots a new container with an empty queue.
     *
     * @return list<Envelope>
     */
    private function queued(): array
    {
        /** @var InMemoryTransport $queue */
        $queue = static::getContainer()->get('messenger.transport.async');

        return array_values(array_filter($queue->getSent(), static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof TaskAssigned));
    }

    /**
     * As the worker does: the received stamp makes the bus handle the message instead of queueing it again.
     *
     * @param list<Envelope> $envelopes
     */
    private function deliver(array $envelopes): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('event.bus');
        foreach ($envelopes as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
        }
    }

    #[Test]
    public function assigneeShouldBeToldByEmailWhoGaveThemWhichTask(): void
    {
        $this->assign($this->task(), $this->colleague);

        $this->deliver($this->queued());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'colleague@example.com');
        self::assertEmailHeaderSame($email, 'Subject', 'You were assigned "Prepare the release notes"');
        self::assertEmailTextBodyContains($email, 'owner@example.com assigned you a task');
        self::assertEmailAddressContains($email, 'From', 'notifications@todo-app.test');
    }

    #[Test]
    public function assignmentShouldOnlyBeQueuedByTheRequest(): void
    {
        $this->assign($this->task(), $this->colleague);

        self::assertCount(1, $this->queued());
        self::assertEmailCount(0);
    }

    #[Test]
    public function nobodyShouldBeToldOfWhatTheyDidThemselves(): void
    {
        $this->assign($this->task(), $this->owner);

        $this->deliver($this->queued());

        self::assertEmailCount(0);
    }

    #[Test]
    public function taskHandedToSomeoneElseBeforeDeliveryShouldNotBeAnnouncedToTheFirstAssignee(): void
    {
        $task = $this->task();
        $this->assign($task, $this->colleague);
        $toColleague = $this->queued();

        $this->assign($task, $this->owner);
        $this->deliver($toColleague);

        self::assertEmailCount(0);
    }

    #[Test]
    public function memberRemovedBeforeDeliveryShouldNotLearnTheTitle(): void
    {
        $this->assign($this->task('Confidential: the reorganisation'), $this->colleague);
        $queued = $this->queued();

        $this->delete($this->route('api_workspace_member_remove', ['id' => $this->workspace->getId()->toRfc4122(), 'userId' => $this->colleague->id()]));
        self::assertResponseStatusCodeSame(204);
        $this->deliver($queued);

        self::assertEmailCount(0);
    }

    #[Test]
    public function taskDeletedBeforeDeliveryShouldNotBeAnnounced(): void
    {
        $task = $this->task();
        $this->assign($task, $this->colleague);
        $queued = $this->queued();

        $this->delete($this->route('api_task_delete', ['id' => $task->getId()->toRfc4122()]));
        self::assertResponseStatusCodeSame(204);
        $this->deliver($queued);

        self::assertEmailCount(0);
    }

    #[Test]
    public function handingWorkOverShouldEmailOncePerTask(): void
    {
        $workspaceTask = fn (string $title): Task => TaskFactory::createOne(['workspace' => $this->workspace, 'title' => $title, 'assigneeId' => $this->owner->id()]);
        $workspaceTask('First');
        $workspaceTask('Second');

        $this->post($this->route('api_workspace_task_reassign', ['id' => $this->workspace->getId()->toRfc4122()]), ['from' => $this->owner->id(), 'to' => $this->colleague->id()]);
        self::assertResponseStatusCodeSame(200);
        $this->deliver($this->queued());

        self::assertEmailCount(2);
    }
}
