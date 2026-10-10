# 0024. The assignee is told by email, from the worker, about the task as it is when the email leaves

- Status: accepted
- Date: 2026-10-11
- Implemented in: #87

## Context

`Task` announces an assignment after its commit since #86
([0023](0023-facts-announced-after-the-commit.md)); nobody listened. The first
notification is the one a team misses most: a task given to someone who does not know it
is theirs.

An email is sent by the worker, seconds or minutes after the assignment. By then the task
may be deleted or handed to someone else, and the assignee may have left the workspace —
and an email carries the task's title out of the application, to someone who may no
longer be allowed to read it ([0011](0011-foreign-task-answers-404.md)).

## Decision

- **A module of its own, `Notification`, listens.** `Task` does not know it is there; it
  reads tasks through `Task\Contract\TaskDirectory` and addresses through
  `Auth\Contract\UserDirectory`, and Deptrac allows it nothing else.
- **`TaskAssigned` goes to the `async` queue**, and the email is sent when the worker
  handles it. The request that assigns does not wait for the mail server.
- **The email is about the task as it is when it is sent.** If the task no longer
  exists, or is no longer assigned to that person, nothing is sent. Whoever left the
  workspace or became a viewer was taken off their tasks
  ([0021](0021-a-task-has-an-assignee-who-can-work.md)), so this one check also keeps the
  title from someone who lost access.
- **Nobody is told what they did themselves.** Assigning a task to oneself sends nothing.
- **One email per task.** Handing ten tasks over sends ten emails.
- **Plain text, built in code**, with the sender set once for all mail (`MAILER_FROM`).

## Alternatives considered

- **Send from the request, synchronously.** No queue, and the email is certain to describe
  what was just done. The request would wait for the mail server and fail with it, after
  the assignment had already committed.
- **Put everything the email needs into the event** — title, emails of both people. The
  worker would need no lookups, and would send what was true at the commit: a title to
  someone removed a minute later, or to someone the task was taken from.
- **Check the assignee's permissions in the workspace instead of the task's assignee.**
  It answers whether they may read the task, not whether it is still theirs; a task
  handed on would still be announced.
- **A listener inside `Task`.** One module fewer, but `Task` would know about mail, and
  every next channel would grow it further.
- **Symfony Notifier.** It routes a notification to channels — email, SMS, chat — by
  importance. With one channel it adds a layer and no decision; it is the candidate when a
  second channel appears.
- **Twig templates for the email.** Worth it for HTML and several emails; for two lines of
  text, a template file is one more place to look.

## Consequences

- The email can arrive late, never earlier than the assignment and only while it still
  holds. If the mail server fails, Messenger retries three times, then keeps the message
  in the failed transport (`make queue-failed`).
- A message can be handled twice — the worker dies after sending, before acknowledging —
  and the assignee then gets the email twice. Recording each notification once is the
  next step on the roadmap.
- Handing many tasks over floods the new assignee's inbox. One email listing them is the
  fix when it is asked for; the events already arrive one per task.
- `UserDirectory` grows a method, `findEmailById`, and `Task` a contract for reading a
  task, `TaskDirectory`. `Task` is the first module read by another.
- A test handles the queued message the way the worker does — taken from the in-memory
  queue and dispatched on its bus with a `ReceivedStamp` — so the rules above are checked
  without a broker.
