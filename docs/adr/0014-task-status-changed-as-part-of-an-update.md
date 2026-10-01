# 0014. Change the task status as part of an update, with its invariant kept in the entity

- Status: superseded by [0015](0015-task-lifecycle-as-explicit-transitions.md)
- Date: 2026-04-02
- Implemented in: #11, #13

## Context

A task gained a `cancellation_reason` (#11) and a history of its status changes (#13). The
status stopped being an independent field: the reason is allowed only while the task is
cancelled and has to be cleared when it leaves that status, and a real change has to
leave a row in `task_status_changes`.

## Decision

- The status stays one of the fields of `PUT /tasks/{id}`. `UpdateTask` applies it together
  with the title, the description and the due date.
- The invariant lives in the entity. `Task::changeStatus()` clears the reason when the
  task leaves `cancelled`, and `Task::setCancellationReason()` refuses a reason on a task
  that is not cancelled.
- `UpdateTask` writes the `task_status_changes` row itself, in the same transaction, and
  only when the status really changed.
- Any status may follow any other. There are no rules about the order.

## Alternatives considered

- **A ternary in the use case** that decides whether to keep the reason. It works, but
  the rule is hidden in an expression and has to be repeated by every caller.
- **A private helper in the use case.** Better to read, but the invariant still belongs to
  the application layer rather than to the task it protects.
- **A framework event or a Doctrine listener** that fixes the reason and writes the
  history. It turns a rule of the task into a side effect nobody sees in the use case.
- **A separate `ChangeTaskStatus` use case.** Premature then: there were no rules about
  which status may follow which, no final statuses and nothing that depends on a
  transition.

## Consequences

- One request and one transaction change a task and record its history.
- A client can put a task into any status, including back out of `completed` or
  `cancelled`, and can create a task that is already cancelled.
- A status change cannot be told from an edit: both are the same request, the same
  `TaskUpdated` event and the same permission.
- The signal named for revisiting: rules about allowed transitions and final statuses.
  They arrived with [0015](0015-task-lifecycle-as-explicit-transitions.md).
