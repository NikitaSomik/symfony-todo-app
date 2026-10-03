# 0016. A blocked task is a status of work in progress, reached and left only from `in_progress`

- Status: accepted
- Date: 2026-10-03
- Implemented in: #70

## Context

Work stops for reasons outside the task: an answer from the client, an access, another
piece of work. The lifecycle of [0015](0015-task-lifecycle-as-explicit-transitions.md)
could not say so: a stuck task looked like any other task in progress, and nothing
recorded why it stood still.

The usual tools answer this in three ways. Jira has a flag ("Impediment") that marks a
card without changing its status, and Azure Boards a `Blocked` field; teams that want a
column of their own add a `Blocked` status to their workflow. Jira, ClickUp, Notion and
Linear also link tasks: one task "is blocked by" another.

## Decision

```
todo → in_progress → in_review → completed
            ↕
         blocked
```

- **`blocked` is a status.** On a board it is its own column, and moving a card there is
  what the user already does in most tools.
- **Only work in progress is blocked**, and unblocking always returns it to
  `in_progress`. A task in `todo` has not started, so nothing stands in its way; one in
  `in_review` is waiting for a reviewer, which is part of the stage. With one way in and
  one way out, the task never has to remember where to return.
- **A block takes a reason**, the `BlockReason` value object with the rules of the
  cancellation reason. It stays on the task while it is blocked and is dropped by any
  transition out of `blocked`. The audit log keeps every reason.
- **A blocked task cannot move forward.** It can be unblocked or cancelled; submitting or
  completing it answers `409`.
- `POST /tasks/{id}/block` with `{"reason": "…"}` and `POST /tasks/{id}/unblock`, built
  like the other transitions.

## Alternatives considered

- **A flag next to the status**, as in Jira and Azure Boards. The task keeps its status,
  so a future limit on work in progress counts it without a special rule. It lost on
  how people work: a column they move cards into is what most teams use, and the state
  of a task would have to be read from two fields.
- **Blocking from several statuses.** It would let a task in `todo` be marked as stuck,
  but unblocking would then need to know which status to go back to — state the task
  would have to keep only for that.
- **A link "blocked by task X"**, which every tool above also has. It says *what* blocks
  the task, and can unblock it when that task is done; it needs relations between tasks
  and background processing, which arrive later (roadmap). The status does not rule it
  out: a link could block and unblock the task through the same transitions.

## Consequences

- The status is one of six. Clients that list statuses — filters, columns — get a new
  value, `blocked`, in `filter[status]` and in the `status` attribute; `block_reason` is a
  new attribute.
- A limit on work in progress (roadmap) has to count `in_progress`, `in_review` and
  `blocked` together. Counting `in_progress` alone would let a team free a place by
  blocking a task and start another one.
- The time a task spends blocked can be read from its status history, from the move into
  `blocked` to the move out of it.
- Stricter than Jira, ClickUp or Linear, which let a blocked task move freely: here it
  has to be unblocked first. The status history stays honest at the cost of one more
  request.
