# 0015. Move a task through its lifecycle with explicit transitions, guarded by the entity

- Status: accepted — supersedes [0014](0014-task-status-changed-as-part-of-an-update.md)
- Date: 2026-10-01
- Implemented in: #65

## Context

Under [0014](0014-task-status-changed-as-part-of-an-update.md) the status was a field of
the update request and any status could follow any other. The roadmap asks for a
lifecycle: work is started, reviewed and then completed, and a finished task stays
finished. That is a rule about which status may follow which — the signal 0014 named.

## Decision

```
todo → in_progress → in_review → completed
  ↓         ↓            ↓
cancelled cancelled   cancelled
```

- **One table of transitions.** `TaskStatus::canTransitionTo()` holds the whole lifecycle.
  `completed` and `cancelled` are final. `in_review` is a new status.
- **The entity guards it.** `Task` has `start()`, `submitForReview()`, `complete()` and
  `cancel(CancellationReason $reason)`. There is no setter for the status: a transition
  outside the table throws `TaskTransitionNotAllowedException`. The reason is an argument
  of `cancel()`, and the `CancellationReason` value object refuses a blank or oversized
  one, so a cancelled task without a usable reason cannot exist.
- **One operation and one endpoint per transition.** `POST /tasks/{id}/start`,
  `/submit-for-review`, `/complete` and `/cancel` call `StartTask`, `SubmitTaskForReview`,
  `CompleteTask` and `CancelTask`. Each names what the user does instead of the field
  that changes.
- **A refused transition answers `409`**, mapped in `framework.exceptions` and logged at
  `info` ([0007](0007-exception-mapping-and-error-normalizer.md)).
- **The history is part of the transition.** `Task` adds the `task_status_changes` row
  itself, in the same method that changes the status, so a status cannot change without
  leaving one. The collection is write-only: it has no getter, and the history is read
  through its repository.
- **The service announces it.** After the transition the service dispatches
  `TaskStatusChanged`, which the audit log listens to. The status, the history row and
  the audit entries are written by one flush in the service's transaction
  ([0012](0012-synchronous-audit-log-one-transaction.md)).
- **Editing is only editing.** `PUT /tasks/{id}` takes the title, the description and the
  due date (`UpdateTaskDetails`). `POST /tasks` always creates a task in `todo`.
- **What belongs in the entity.** A rule that can be checked from the task's own fields
  lives in `Task`. A rule that needs anything else — other tasks, the current user, the
  clock — lives in the service.

## Alternatives considered

- **Symfony Workflow (`state_machine`).** The framework's own tool for this. It was built
  end to end on the branch `feature/task-lifecycle-workflow`, kept for reference and never
  merged: the same transitions, the same endpoints, an identical OpenAPI document. The
  component fits without friction — the enum in the configuration, the workflow declared
  inside the module, the service autowired by name — and the lifecycle code is the same
  size (347 lines against 342). It lost on what it asks of the entity:
  - `MethodMarkingStore` writes the status through a public `setStatus()`, so the task no
    longer protects itself. A test on the branch puts a new task straight into
    `completed`, and cancels one without a reason.
  - The data of a transition — the time and the cancellation reason — travels in an
    untyped `$context` array with optional keys.
  - `NotEnabledTransitionException` is not written for clients, so a refused transition
    answers a bare `Conflict` instead of naming the two statuses
    ([0007](0007-exception-mapping-and-error-normalizer.md)); bringing the message back
    takes a wrapper in every service.
  - The rules can no longer be tested without the container.

  What it gave in return: `workflow:dump` draws the lifecycle, and guard events are there
  for rules that other modules contribute. It wins where a model cannot guard itself, or
  where such guards exist.
- **One `ChangeTaskStatus` operation that takes the target status.** One class instead of
  four, but the reason becomes an optional argument that only one target needs, and the
  operations stop being named after what the user does.
- **`PATCH /tasks/{id}` with only the status.** It answers "update one field" and keeps
  the status a field: the request still says what the data should become, not what
  happened.
- **Transition rules in the services.** Each service would check the current status
  itself; the lifecycle would be spread over four classes and could be bypassed by the
  next one.
- **A shared service that writes the history row after the transition.** The first
  version of this change. The row then depends on every caller remembering the second
  step; inside the entity it cannot be forgotten.

## Consequences

- This is a breaking change of the API. `status` and `cancellation_reason` are no longer
  read from the body of `POST /tasks` and `PUT /tasks/{id}`; as with any unknown field,
  they are ignored rather than rejected.
- A transition is one `UPDATE` of `tasks`, one history row and its audit entries, in one
  transaction; a refused one changes nothing.
- The lifecycle is tested without the framework: the table in `TaskStatusTest`, the
  entity in `TaskTest`.
- The transition methods take the time as an argument, because an entity cannot ask a
  clock. A transition does not load the history that is already there — a test counts the
  SQL.
- The event is still the service's job: a new transition service that forgets to dispatch
  `TaskStatusChanged` leaves no audit entry. Creating, updating and deleting a task work
  the same way. Letting the task collect its own events is the step to take together with
  Messenger (roadmap).
- Fixtures put a task into a status by reflection, the same way they set its creation
  time. Production code has no such door, but a bulk DQL `UPDATE` would bypass the rules.
- A cancelled task cannot be reopened and a completed one cannot be cancelled. Changing
  that is a change of the table, not of the callers.
- Two transitions of the same task that run at the same moment are not detected: both
  read the same status and both succeed. One owner per task makes that unlikely today;
  shared projects (roadmap, Phase 2) are the signal to add optimistic locking.
- Signal to revisit Workflow: transitions that need guards contributed by other modules,
  or enough per-transition logic that the services stop being a few lines each.
