# 0023. A module announces a fact to the others after its transaction has committed

- Status: accepted
- Date: 2026-10-10
- Implemented in: #86

## Context

Notifications are next, starting with an email to the person a task was assigned to.
Unlike the audit log ([0012](0012-synchronous-audit-log-one-transaction.md)) or taking
tasks off a member who left ([0021](0021-a-task-has-an-assignee-who-can-work.md)), an email
does not have to happen in the request: it is slow, it calls another system, and the data
is consistent without it. The queue for such work exists since #84.

Two things were missing. `Task` had no event that says what happened: `TaskUpdated`
carries the state before and after, which serves the audit log but leaves every other
listener to work out that an assignment took place. And nothing said when an event may
leave the module. Every use case runs in `wrapInTransaction`, which flushes inside the
transaction: an event sent "after the flush" is still sent before the commit, and a
rollback would leave a listener acting on an assignment that never happened.

## Decision

- **A fact for other modules is a class in the module's `Contract/`**, named for what
  happened: `Task\Contract\TaskAssigned` — task, workspace, assignee and who assigned.
  Deptrac now has a `TaskContract` layer, as `Auth` and `Workspace` have.
- **It is published after `wrapInTransaction` returns**, by the service that made the
  change, through `Shared\Messaging\EventPublisher`. Nothing is published for a change
  that was refused or rolled back, or for an assignment that changed nothing.
- **Facts travel on their own bus, `event.bus`, which allows no handlers.** A module
  announces what happened without knowing whether anyone listens.
- **A fact that cannot be sent is logged, and the request still succeeds.** The change is
  committed; answering `500` would tell the client it was not, and a retry would repeat
  it.
- **Consistency stays synchronous.** What must hold when the request ends — the audit
  log, taking tasks off someone who can no longer work — keeps using the event dispatcher
  inside the transaction. The bus is for side effects.

## Alternatives considered

- **Listen to `TaskUpdated` and compare the states.** No new class, but every listener
  outside `Task` would have to know the shape of the task's state to learn that it was
  assigned.
- **Publish from a listener inside the transaction, or on Doctrine's `postFlush`.** No
  change to the services. The message leaves before the commit: an assignment rolled back
  after it would still be announced, which is worse than one announcement lost.
- **A transactional outbox.** The message is written to a table in the same transaction and
  a relay sends it to the broker, so nothing is lost. Symfony 8.2, due in November 2026,
  brings one to Messenger; built by hand now, it would be custom infrastructure replaced
  within weeks.
- **The entity records its events, released after the flush.** The event could not be
  forgotten, the way the entity already guards its status
  ([0015](0015-task-lifecycle-as-explicit-transitions.md)). Doctrine has no mechanism for
  it, and when to release the events is the very question above; with two services
  publishing one event, an explicit call is easier to follow.
- **Let the default bus carry facts.** One bus fewer, but it fails a message nobody
  handles, which an announcement is allowed to be.

## Consequences

- An announcement can be lost: the commit succeeds and the broker is unreachable a moment
  later. The log says so; nothing retries it. For a notification that is acceptable. A
  fact that must not be lost — billing, an integration another system relies on — is the
  signal to adopt the outbox of Symfony 8.2, which moves the publishing back inside the
  transaction.
- A service that publishes must not run inside another transaction: "after it returns"
  would then be before the real commit. Nothing does today; `wrapInTransaction` gives no
  warning if something starts to.
- Handing work over publishes one fact per task, after the commit of the whole hand-over.
  A listener that writes to a person per fact would send them one message per task; the
  listener decides whether to group them.
- Nobody listens to `TaskAssigned` yet, and in this pull request it travels synchronously
  to no handler. The first listener, the email to the assignee, routes it to the queue.
- Tests read published facts through a handler registered only in the test container,
  `Tests\Support\PublishedEvents`.
- Since #87 `Notification` listens ([0024](0024-the-assignee-is-told-by-email-from-the-worker.md)):
  `TaskAssigned` is routed to the `async` queue, and tests read it from the in-memory
  queue instead; `PublishedEvents` is gone.
