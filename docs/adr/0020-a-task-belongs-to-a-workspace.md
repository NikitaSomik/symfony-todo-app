# 0020. A task belongs to a workspace, and membership decides who reaches it

- Status: accepted
- Date: 2026-10-06
- Implemented in: #78

## Context

Workspaces existed, with members and roles ([0019](0019-workspaces-members-and-roles.md)),
but a task still belonged to the user who created it and nobody else could see it
([0011](0011-foreign-task-answers-404.md)). `Task` had just stopped importing `Auth`'s
internals (#77), so the rule of access could change without touching how a task names a
user.

## Decision

- **Every task is in exactly one workspace.** `Task` holds a relation to
  `Workspace\Contract\WorkspaceReference` with a foreign key: a task without its
  workspace means nothing ([0018](0018-modules-meet-through-contracts.md)).
- **The creator is a fact, not a right.** `creator_id` stays a plain id and gives no
  access. What a user may do with a task comes only from their role in its workspace.
- **A stranger gets `404`, as before.** `MemberTaskValueResolver` loads the task and asks
  `Workspace\Contract\WorkspaceAccess` for the caller's role; no role answers like a
  missing task, before the body is validated.
- **A viewer reads and nothing else.** `TaskVoter` answers `403` to a viewer on every
  request that changes a task or creates one. The order of answers is the one workspaces
  use: `404`, `403`, `422`, `409`.
- **The workspace is in the address where it is the subject.** A task is created and
  listed at `/workspaces/{id}/tasks`. One task and its transitions stay at `/tasks/{id}`:
  the id is unique by itself.

## Alternatives considered

- **A task outside any workspace, for personal use.** Two kinds of task, two rules of
  access. Every user already has a personal workspace for that.
- **The workspace in the request body or in a filter.** One list endpoint instead of two,
  but creating a task would have to validate the body before it could know whether the
  caller may even see the workspace — the `404` before `422` order would be lost.
- **One query that joins `tasks` with `workspace_members`.** One round trip instead of
  two. It reads another module's table from `Task`, which
  [0018](0018-modules-meet-through-contracts.md) allows only for a named read that sorts
  or filters by a foreign field; this is neither.
- **Leave the viewer unrestricted until the table of rights is built.** Smaller change,
  and `main` would hold a viewer who can delete tasks.
- **Keep `GET /tasks` as the tasks of every workspace the user is in.** Built, then
  removed before the merge: tasks of several teams in one list answer no question a
  member has. The list that crosses workspaces is "assigned to me", and it needs an
  assignee first.
- **Move the existing tasks into their creator's personal workspace.** A data migration
  for a database that holds only generated data. The migration stops on a non-empty
  `tasks` table instead and says how to recreate it.

## Consequences

- Loading a task for a member takes two queries, the task and the role, and a request
  that changes it a third: the voter asks for the role again. A join would be one.
- `403` is back on the task endpoints, which [0011](0011-foreign-task-answers-404.md) had
  removed, and with it a `TaskVoter`. It now tells a member what is not theirs to do; it
  never tells a stranger that a task exists.
- A member who leaves a workspace loses the tasks they created there.
- The rule "a viewer only reads" lives in `Task`, in the voter; `Workspace` only names the
  roles. A member may still delete a task: who may do what beyond reading and writing is
  the next step.
- Search is filtered by the workspace now, through `idx_tasks_workspace_id`. The
  measurements of [0013](0013-task-search-postgresql-full-text.md) were taken per number
  of tasks sharing that filter, and a team's workspace holds more tasks than one user did.
- The development database is recreated with `make db-fresh`.
