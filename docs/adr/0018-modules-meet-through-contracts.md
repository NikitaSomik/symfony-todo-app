# 0018. Modules meet through contracts, ids and events

- Status: accepted
- Date: 2026-10-04
- Implemented in: #76

## Context

Deptrac checks which module imports which ([0001](0001-feature-based-modular-monolith.md)).
It also showed how wide the contact between modules is: `Task` reaches into `Auth` for
its `User` entity in 33 places and into `AuditLog` for eight different classes. A
dependency was allowed or forbidden as a whole; no module said which of its classes the
others may rely on.

A third module, `Workspace`, needs users, is needed by tasks, and has to appear when a
user registers — which would make `Auth` and `Workspace` depend on each other.

## Decision

Rules for every new dependency between modules. Existing code moves to them when it is
touched, not for its own sake.

- **A module's `Contract/` namespace is its public face.** Other modules may import only
  that. In `deptrac.php` a module with a contract is two layers — `AuthContract` and
  `Auth` — and only the first appears in another module's rule.
- **Another module's data is referenced by id.** A membership holds a `user_id`, not a
  Doctrine relation to `User`. The JSON:API response already speaks that way: a
  relationship is a type and an id.
- **A cycle is broken with an event.** `Auth` dispatches `Contract\UserRegistered` inside
  the registration transaction; `Workspace` listens and creates the personal workspace.
  `Auth` still knows nothing about `Workspace`, and a failure rolls the registration
  back.
- **No SQL join across modules by default.** Foreign data is fetched through the
  contract, for a page of rows at once. A join is allowed in a query class named for it,
  when a list has to be sorted or filtered by another module's field; that is coupling
  to the other module's schema and is recorded as such. There is none today.
- **A foreign key across modules only where the child means nothing without the
  parent.** A membership of a user that is gone means nothing, so `workspace_members.user_id`
  has one; an audit record states a fact and has none
  ([0017](0017-audit-log-as-its-own-module.md)).

## Alternatives considered

- **Keep allowing whole modules.** The cheapest, and what Deptrac checked until now. The
  contact grows with every feature, and nothing shows a module's author what others
  depend on.
- **Doctrine relations between modules.** The usual Symfony way: `$membership->getUser()`.
  It ties one module's entity to another's, and the entity becomes part of everything
  that touches it — the 33 places in `Task` are that.
- **A strict version: no foreign key and no join across modules, ever.** What a team
  preparing to split a module into a service does. With one database and no such plan it
  would cost database integrity and simple list queries, and buy an option nobody asked
  for.
- **`Auth` calling `Workspace` on registration.** Direct and easy to follow, but it makes
  the two modules depend on each other.

## Consequences

- Doctrine creates a foreign key only for a relation. For a plain id the key is added to
  the schema by a listener on `ToolEvents::postGenerateSchema`
  (`Workspace\Persistence\MembershipUserForeignKey`), so `migrations:diff` and
  `schema:validate` see it. The per-table event cannot be used: Doctrine attaches the
  keys of real relations afterwards, and a table replaced there loses them.
- Fetching through a contract is one more query where a join would be none.
- `$membership->getUser()` does not exist. Showing a member's email takes a call to
  `Auth`'s contract.
- `Task` still imports `Auth\Entity\User` and eight `AuditLog` classes. It moves to the
  contracts when tasks move into workspaces.
- Registration now flushes twice in one transaction: the event carries the user's id,
  and the id comes from the database.
