# 0022. Code asks for a permission; a role is a named set of them

- Status: accepted
- Date: 2026-10-08
- Implemented in: #81

## Context

Four places decided what a user may do by comparing roles: three in `Task` asked whether
the role was "not a viewer", one in `Workspace` whether it was "an owner". The same
knowledge — who can work on tasks — was written three times, and negatively: a fourth
role would have counted as working everywhere without anyone deciding so. And a member
could delete a task, because writing and deleting were one check
([0020](0020-a-task-belongs-to-a-workspace.md)).

## Decision

- **A permission names something code can ask for.** `Workspace\Contract\WorkspacePermission`
  has four: `view`, `manage_workspace`, `work_on_tasks`, `delete_tasks`.
- **A role is a set of permissions, and one place says which.**
  `WorkspaceRole::permissions()` is the whole table:

  | | view | manage workspace | work on tasks | delete tasks |
  |---|---|---|---|---|
  | owner | yes | yes | yes | yes |
  | member | yes | | yes | |
  | viewer | yes | | | |

- **Everyone has a set of permissions in every workspace; a stranger's is empty.** Nothing
  outside `Workspace` is handed a role that may be missing: `WorkspaceAccess` answers
  `can()` and `permissionsOf()`, and `MemberPermissionsChanged` carries what a member may
  do from now on. Someone who is gone is announced by an event of its own,
  `MembershipEnded`, not by an empty set that has to be read as "gone".
- **The role is not part of the contract.** `WorkspaceRole` lives inside `Workspace`.
  Another module cannot compare roles: Deptrac refuses the import.
- **Voters still refuse the request.** `TaskVoter` and `WorkspaceVoter` map their
  attribute to a permission; `403` and the order of answers are unchanged.
- **Deleting a task is for owners.** Work that is no longer needed is cancelled, with a
  reason that stays ([0015](0015-task-lifecycle-as-explicit-transitions.md)); deleting
  removes the task from every list and is meant for one created by mistake.
- **The roles are fixed in code.**

## Alternatives considered

- **Hand other modules the role, `null` for a stranger.** Built first, in this pull
  request. Every reader had to check for the missing role before asking it anything, and
  the checks spread: three of them within a day.
- **One event for every change of a membership**, with an empty set of permissions for
  someone removed. Built first, in this pull request. The empty set needed a named
  constructor to say what it meant, and the constructor needed explaining: two facts were
  sharing one name.
- **A role for someone who is not a member** (`none`). No `null`, and a case that must
  never be stored, never offered when a member is added and never documented.
- **Keep comparing roles.** Nothing to build. Every new action adds one more comparison,
  and every new role has to be remembered in all of them.
- **Roles and permissions in the database**, as `spatie/laravel-permission` keeps them.
  That is what custom roles need — an owner defining their own. For three roles that never
  change it is tables, an interface to edit them and a cache, holding constants.
- **Symfony's role hierarchy** (`ROLE_*` in `security.yaml`). It is one hierarchy for the
  whole application; here a user is an owner in one workspace and a viewer in another.
- **A permission per action** — create, edit, start, assign and so on. More precise, and
  no role differs in any of them today: every one would be given to the same two roles.

## Consequences

- A new role is one line in `permissions()`, and `match` fails loudly on a role that has
  none. A new permission is a case and a column of that table.
- A member who deletes a task now gets `403`.
- Reading is a permission like the others, so "may this user see the task" and "may they
  change it" are the same question with a different argument.
- "At least one owner" still speaks of the role itself, not of a permission
  ([0019](0019-workspaces-members-and-roles.md)): it is a rule about who a workspace
  belongs to, not about what someone may do.
- A response does not say what its reader may do. A task still lists every transition its
  status allows, also to a viewer who will get `403` on each.
- The voter asks for the role again after the resolver has: a request that changes a task
  still takes three queries ([0020](0020-a-task-belongs-to-a-workspace.md)).
- If custom roles are ever wanted, `can()` is the one place that moves to the database.
