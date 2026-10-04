# 0019. Work is shared in workspaces: members, three roles, at least one owner

- Status: accepted
- Date: 2026-10-04
- Implemented in: #76

## Context

A task belonged to one user, and nobody else could see it
([0011](0011-foreign-task-answers-404.md)). The tracker is meant for a small team:
several people have to work on the same tasks, with different rights.

Tools built for teams put every piece of work into a container of people — a workspace in
Trello, Linear, Notion and ClickUp — and none of them lets work exist outside one. Trello
moved the boards people had kept personally into workspaces when it made that rule.

## Decision

- **A workspace is a set of members.** The module `src/Workspace/` owns `workspaces` and
  `workspace_members`. `Workspace` is the aggregate: members are added, removed and given
  roles only through it.
- **Three roles:** `owner` manages the workspace and its members, `member` works, `viewer`
  reads. One role per user in a workspace.
- **A workspace keeps at least one owner.** The last one can be neither removed nor given
  another role; several owners are allowed. The entity enforces it and answers `409`.
- **Every user has a personal workspace**, created at registration
  ([0018](0018-modules-meet-through-contracts.md)). It is an ordinary workspace named
  "Personal": people can be added to it later.
- **A member is added by the email of a registered user**, by an owner.
- **Who may do what is answered in three steps.** A workspace the user is not a member of
  answers `404`, like a missing one — the resolver loads it only among the user's own. A
  member without the right answers `403`, from `WorkspaceVoter`. Only then is the body
  validated (`422`) and the rule checked (`409`).
- **Membership changes are history**: created, renamed, member added, role changed,
  member removed go to the audit log in the same transaction
  ([0012](0012-synchronous-audit-log-one-transaction.md)).
- Tasks are not in workspaces yet; that is the next step.

## Alternatives considered

- **Tasks without a workspace for personal use, workspaces for teams** — how Todoist
  keeps personal projects apart from team ones. Every rule about tasks would then exist
  twice: "is it yours?" and "are you a member?". A personal workspace keeps one rule.
- **Invitations the invited person accepts.** The right flow for strangers, and it does
  not reveal who is registered. It needs tokens, expiry and email; deferred.
- **A single owner.** Simpler, but the workspace is stuck when that person leaves.
- **Permissions checked inside the services.** Each service would throw its own
  "forbidden"; a voter keeps the question "may this user manage this workspace?" in one
  place and is Symfony's own tool for it.

## Consequences

- Adding a member by email tells an owner whether that email is registered: an unknown
  one answers `422`. Only an authenticated owner can ask; invitations would remove it.
- `403` exists again, next to `404`: a stranger cannot learn that a workspace exists, a
  member learns that an action is not theirs.
- A member can leave; the last owner cannot. Deleting a workspace is not possible yet —
  what happens to its tasks has to be decided first.
- A workspace loads all its members to answer who may do what. Fine for teams of this
  size; a workspace with thousands of members would need the role read by a query.
- The fixtures give every user a personal workspace and every tenth one a team; the
  development database is recreated with `make db-fresh` rather than migrated.
