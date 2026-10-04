# 0017. The audit log is a module of its own, and names the actor by id

- Status: accepted
- Date: 2026-10-04
- Implemented in: #73

## Context

The audit log lived in `src/Shared/AuditLog`, and each record held a Doctrine relation to
`Auth\Entity\User` with a foreign key `ON DELETE SET NULL`. Two things followed.

`Shared` — the code every module may use — depended on a module. It was the one broken
boundary recorded in [0001](0001-feature-based-modular-monolith.md). And the record of who
acted depended on the user still existing: deleting a user would have erased the actor
from every record that user had left.

Workspaces with members and roles come next (roadmap), and their history belongs in the
same log. That would have added a second module to what `Shared` depends on.

## Decision

- **The audit log is the module `src/AuditLog/`**, with its own `di.php`. It has a table,
  an entity and a resource — a feature, not a utility. `Shared` keeps only technical code
  and depends on no module again.
- **Modules write to it; it knows none of them.** A module decides what is worth a record
  and passes plain values to `AuditLogLogger` — `Task` does it in `TaskAuditLog`. The
  dependency points from the module to the audit log.
- **The actor is a plain `actor_id`**: an indexed integer, no foreign key, no Doctrine
  relation, no copy of the name or the email. What a person did to a task is history, and
  the history has to stay when that person leaves: with a foreign key the database would
  either erase the id or refuse to let the user go. `null` means no user made the change.
- **The subject stays a polymorphic pair** — `entity_type` and `entity_id` without a
  foreign key ([0012](0012-synchronous-audit-log-one-transaction.md)).
- The response still exposes the actor as the `user` relationship with the `users` type,
  so the API did not change.

## Alternatives considered

- **Keep the foreign key with `ON DELETE SET NULL`.** The database then guarantees the
  actor exists when the record is written — a guarantee nothing needed, since the actor is
  the authenticated user of the request. On deletion it erases the id, and with it the
  one thing still known: that the same person made these changes.
- **A foreign key that forbids deleting a user with history.** It would block removing a
  person's data on request.
- **A copy of the actor's name next to the id.** It survives deletion, but it goes stale
  when a name changes, and it copies personal data into every record — which then has to
  be found and erased on a deletion request. The display name is resolved from `Auth` when
  a record is shown; a user that is gone is shown as such.
- **The audit log subscribing to every module's events.** The modules would know nothing
  about it, but it would have to know all of them — the dependency reversed, and one
  module coupled to the whole application.

## Consequences

- `Shared` imports nothing from a module, and `AuditLog` only from `Shared`. The
  violation recorded in 0001 is gone, and Deptrac keeps it from coming back (#75).
- Tables of different modules are no longer tied by a foreign key here. `tasks.user_id`
  keeps its key: a task belongs to its owner, and `Task` may depend on `Auth`.
- The database no longer checks that `actor_id` points at a user. A record written past
  the application could name anyone.
- `AuditLogEntityType` still lists the subjects the log knows — today `task`. A new
  module adds a case there; if that list starts to change often, the type becomes a
  string the writing module supplies.
- What happens to a user's identity on deletion is `Auth`'s decision, to be taken when
  that feature exists; the log works with any of them.
