# 0012. Write the audit log synchronously, in the same transaction as the change — one flush per use case

- Status: accepted
- Date: 2026-04-07
- Implemented in: #20, #61

## Context

Every change to a task leaves an audit entry: who did what, with the old and new values.
The entry is a side effect of the use case, so the use cases should not build it
themselves — but it must not disagree with the data either: a changed task without an
entry, or an entry for a change that was rolled back, makes the history untrustworthy.

## Decision

- `CreateTask`, `UpdateTask` and `DeleteTask` dispatch `TaskCreated`, `TaskUpdated` and
  `TaskDeleted`. Listeners turn the event into audit entries through `TaskAuditLog`; the
  use case knows nothing about the audit log.
- The dispatch is synchronous and happens inside `EntityManager::wrapInTransaction()`.
  Listeners only `persist()`; the task, its `TaskStatusChange` and the audit entries are
  written by one flush and committed together, or not at all.
- **One transaction and one flush per use case.** A service that is a complete operation
  owns the transaction boundary; `wrapInTransaction()` flushes before it commits, so the
  service does not call `flush()` itself. A building block used inside another operation
  (`IssueRefreshToken`, `AuditLogLogger`) never flushes.
- Audit entries live in one shared `audit_logs` table, addressed by `entity_type` and
  `entity_id` without a foreign key, so the entry of a deleted task outlives the task.

## Alternatives considered

- **Write the audit entry in an asynchronous job.** It takes the write out of the request,
  but the entry becomes eventually consistent: a task can be saved while its entry fails,
  and retries and deduplication become part of the feature. For a history that users are
  meant to trust, a missing or late entry is the worse trade.
- **A task-only `task_audit_logs` table.** It looks safer, with a foreign key — but an
  audit record has to outlive what it describes, so the key would either delete the
  history with the task or block the deletion. Without the key, a per-entity table buys
  nothing over a shared one. The shared table takes the polymorphic pair knowingly —
  integrity of `entity_id` rests on the code — and is ready for the history of members and
  roles in the next release. Its one real cost was `Shared` depending on `Auth` for the
  actor, removed in [0017](0017-audit-log-as-its-own-module.md).
- **An explicit `flush()` inside `wrapInTransaction()`.** The code until #61. It is
  redundant — the wrapper flushes again before commit — and the second flush is not free:
  measured in #61, it turned one phantom change into an extra `UPDATE` on every write.

## Consequences

- The request pays for the audit write, and a failing listener fails the whole operation
  with a `500`. Tests prove the rollback for create, update and delete.
- `TaskWriteQueriesTest` counts the SQL of a request through the Symfony profiler:
  creating a task is one `INSERT` on `tasks`, renaming it one `UPDATE`.
- `entity_id` has no foreign key, so the database does not guarantee that it points to an
  existing row; the entry carries a snapshot of the data instead.
- An update writes one entry per changed field, so a `PUT` that changes four fields adds
  four rows.
- Signal to revisit: a listener that calls an external system or does slow work. That
  side effect does not belong in the transaction and would go to Messenger (roadmap).
