# 0010. Give a task its identity in the application: a UUIDv7 from `TaskIdGenerator`

- Status: accepted
- Date: 2026-04-06
- Implemented in: #19

## Context

A task was identified by an integer the database generated on `INSERT`, so a new task had
no id until the first `flush()`. The audit log ([0012](0012-synchronous-audit-log-one-transaction.md))
needs the id of the task it describes. With a database-generated id, creating a task took
two flushes: one to get the id, one to store the audit entry.

## Decision

- A task is identified by a UUIDv7 generated in the application. `Task::__construct()`
  requires the id, so a task without an identity cannot exist.
- `CreateTask` asks the `TaskIdGenerator` interface for the id. `UuidV7TaskIdGenerator` is
  the only implementation, wired by an alias in `src/Task/di.php`.
- PostgreSQL stores the id in a native `uuid` column; the API exposes it in its RFC 4122
  string form.

## Alternatives considered

- **Keep the database-generated integer.** The audit entry then needs either a second
  `flush()` inside the use case or an asynchronous job, and the second option gives up the
  atomicity that [0012](0012-synchronous-audit-log-one-transaction.md) chose.
- **Call `Uuid::v7()` in `CreateTask`.** It works, but puts a library call and the choice
  of UUID version into the use case. With the interface the use case states what it needs
  — a new task identity.
- **Inject Symfony's `UuidFactory`.** It is one service for the whole application, and
  the version it creates is the framework-wide `framework.uid.default_uuid_version`
  setting. The identity of a task would then be decided outside the `Task` module: if the
  setting changed to `4` for another reason, task ids would silently stop growing with
  time, and the list ordering relies on that. The module's own interface keeps the choice
  inside the module.
- **`TaskRepository::nextIdentity()`**, the pattern from DDD literature. `TaskRepository`
  here is a Doctrine query repository, not a domain collection; a small dedicated
  generator gives the same result without widening its role.

## Consequences

- Creating a task is one `INSERT` in one flush; the id is known before `persist()`, so the
  `TaskCreated` event carries it.
- UUIDv7 grows with creation time. The task list uses the id as its last sort key (#45)
  and lets it follow the requested direction (#62), so paging is stable and tasks created
  in the same second still come in creation order.
- The id reveals when the task was created. The API returns `created_at` anyway, so
  nothing new is disclosed.
- An id cannot be guessed by counting, but that is not the access control: someone else's
  task is hidden by [0011](0011-foreign-task-answers-404.md).
- The id takes 16 bytes instead of 4 or 8, in the table and in every index and foreign
  key that references it.
- `TaskStatusChange` and `AuditLog` keep database-generated integer ids: nothing needs
  their identity before the flush.
- `TaskIdGenerator` is an interface with a single implementation and no test double.
  Against a direct `Uuid::v7()` call it adds only a name for the intent. It stays because
  it costs one alias; if it never gains a second implementation or a test double, inlining
  it is the honest simplification.
