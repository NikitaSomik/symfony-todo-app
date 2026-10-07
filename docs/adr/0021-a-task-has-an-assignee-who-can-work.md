# 0021. A task has one assignee, and only someone who can work in its workspace

- Status: accepted
- Date: 2026-10-07
- Implemented in: #79

## Context

A task belonged to a workspace and every member could work on it
([0020](0020-a-task-belongs-to-a-workspace.md)), but nothing said whose it was. A team
needs that, and it needs an answer to what happens to someone's tasks when they stop
being able to work on them.

## Decision

- **A task has at most one assignee**, and a new task has none. `assignee_id` is a plain
  id without a foreign key ([0018](0018-modules-meet-through-contracts.md)): what has to
  hold is not that the user exists but that they can work in this workspace, and a key
  to `users` would not check that.
- **Only an owner or a member can be an assignee.** A viewer cannot work on a task, so a
  viewer cannot hold one. `AssignTask` asks `Workspace\Contract\WorkspaceAccess` for the
  role and answers `422` otherwise.
- **Assigning is its own action**, like a transition: `PUT /tasks/{id}/assignee` and
  `DELETE /tasks/{id}/assignee`. Anyone who may change the task may assign it, to
  themselves or to someone else.
- **A finished task keeps its assignee.** Once a task is completed or cancelled, who held
  it is history; changing it answers `409`.
- **Someone who can no longer work is taken off their unfinished tasks.** `Workspace`
  announces `Contract\MembershipChanged` when a member is removed, leaves or gets another
  role; `Task` listens and unassigns, in the same transaction, with an audit entry per
  task in the name of whoever caused the change.

## Alternatives considered

- **Refuse to remove a member who still holds tasks.** Nothing is left hanging, but an
  owner cannot remove someone until every task of theirs is reassigned by hand — and a
  removal is usually urgent.
- **Leave the tasks assigned.** No code, and tasks stay with someone who cannot open them
  until somebody notices.
- **Let a viewer be an assignee.** One rule fewer. The task would be held by the one
  member who is not allowed to move it.
- **The assignee as a field of the task update.** One endpoint fewer. `PUT /tasks/{id}`
  replaces the title, description and due date; with the assignee in it, every edit of a
  title would have to send the assignee back or drop it.
- **A foreign key from `assignee_id` to `users`.** It guarantees the wrong thing: the user
  exists, not that they are in the workspace.

## Consequences

- Whether the assignee can work is checked when the task is assigned, by a query, before
  the transaction. Someone demoted in that instant can still receive the task; the next
  change of their membership takes it off them.
- Removing a member loads their unfinished tasks and writes one `UPDATE` and one audit
  `INSERT` per task, all in the one flush of the removal: about 0.2 s for 500 tasks in the
  test environment. The listener opens no transaction of its own — a nested one flushes,
  and a flush per task rechecks every task loaded so far: built that way first, the same
  500 tasks took 2.7 s. Thousands of tasks per member would be the signal to unassign
  with one statement or in the background.
- `Workspace` does not know that `Task` listens. A listener that fails rolls the removal
  back: the member stays, and the request answers `500`.
- A finished task can name an assignee who has since left the workspace. A list of "my
  tasks" has to check membership, not only the assignee.
- Getting the role back does not bring the tasks back.
- A task cannot be created with an assignee, and tasks cannot be filtered by assignee
  yet.
