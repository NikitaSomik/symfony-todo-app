# 0030. A task in review can be sent back to work, without a reason of its own

- Status: accepted
- Date: 2026-10-27
- Implemented in: #103

## Context

Under [0015](0015-task-lifecycle-as-explicit-transitions.md) a task in `in_review` had two
ways out: `complete` and `cancel`. A reviewer who found something to fix could accept
work that was not ready, cancel work that was still needed, or leave the task in
`in_review` while it was being changed again. None of them says what is happening.

## Decision

- **`request_changes`: `in_review` → `in_progress`.** One more row of `TaskTransition`,
  `Task::requestChanges()`, the `RequestTaskChanges` service and
  `POST /tasks/{id}/request-changes`, built like the other transitions: the same
  permission (`work_on_tasks`), a history row, a `TaskStatusChanged` event for the audit
  log, `409` from any other status. A task in review links it.
- **Back to `in_progress`, not to `todo`.** The work has started and the assignee keeps
  it; the task goes to review again with `submit_for_review` when it is ready. The limit on
  work in progress planned in [0016](0016-blocked-is-a-status-of-work-in-progress.md)
  counts both statuses, so a task sent back would take no new place.
- **The transition carries no text.** What to change is feedback, often more than one
  point, and it is a discussion: it belongs in comments on the task, as in issue trackers,
  or where the work itself is reviewed. The task has no comments yet; until it does, the
  feedback is given elsewhere.

## Alternatives considered

- **Back to `todo`.** It would say the work has not started, and the assignee would have to
  start it again.
- **A status of its own, `changes_requested`.** One more column on a board and more
  transitions out of it, for a task that is in fact in progress. The status history
  already shows each time a task came back from review.
- **A reason stored on the task, as `block_reason` is.** One field holds one text: several
  points cannot be answered or closed one by one, and the next return overwrites it.
  Required, it would also force a reviewer who has already written the feedback elsewhere
  to repeat it.

## Consequences

- A task can go round `in_progress` → `in_review` → `in_progress` any number of times; the
  status history keeps each round.
- Anyone who can work on tasks may send one back, the assignee included, as anyone may
  complete one. Who may review is a workspace rule still to come.
- The assignee is not told that a task came back. An email like the one on assignment
  ([0024](0024-the-assignee-is-told-by-email-from-the-worker.md)) is the step to add when
  it is needed.
- Comments on a task are the place for the feedback, and the signal to let
  `request_changes` take one.
