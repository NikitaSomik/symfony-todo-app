# 0001. Organise the code as a feature-based modular monolith

- Status: accepted
- Date: 2026-02-27
- Implemented in: #3, refined in #34 and #35

## Context

The project started from the Symfony skeleton, which groups code by technical layer:
`src/Controller`, `src/Entity`, `src/Repository`, one `services.yaml` and one `routes.yaml`.
With a single `Task` resource that was fine. Authentication, projects and an audit log were
already planned, and a layered layout spreads each of them across every directory: a
change to one feature touches several folders, and nothing shows where one feature ends and
the next begins.

## Decision

Every feature is a module under `src/<Module>/` that owns its whole vertical slice —
controllers, DTOs, entities, repositories, services, API resources.

- A module registers itself. `src/<Module>/di.php` declares its services and
  `src/<Module>/routing.php` its routes; `config/services.php` and `config/routes.php` only
  import `../src/**/di.php` and `../src/**/routing.php`. Adding a module never edits a
  central file.
- A module's services and routes are declared in PHP, not YAML, so class names in them are
  real references that the IDE can navigate and rename. Package configuration under
  `config/packages/` stays in YAML, as Symfony's recipes write it.
- Doctrine maps each module's `Entity/` directory as its own mapping, and only that
  directory.
- `src/` holds only production code. Test factories and stories live in `fixtures/`, under
  `autoload-dev` (#35). They used to sit inside the modules, and those classes, built on the
  dev-only Foundry, broke the production build because the Doctrine mapping and the
  `di.php` glob still reached them (#34).
- Code used by more than one module lives in `src/Shared/`.
- Dependencies point one way: `Task` may use `Auth` (a task has an owner), `Auth` knows
  nothing about `Task`.

The modules today are `Auth`, `Task` and `AuditLog`, with `Shared` next to them.

## Alternatives considered

- **Keep the layered skeleton layout.** The Symfony default and the least surprising for
  a newcomer. Lost because cohesion is by technical role, not by feature: the files that
  change together live apart.
- **Symfony bundles per module.** The framework's own unit of modularity. Lost because a
  bundle is built for reuse across applications — extension class, configuration tree,
  compiler passes — and none of that is needed inside one application. `di.php` and
  `routing.php` give the same self-registration at a fraction of the ceremony.

## Consequences

- A module can be read, reviewed and — if ever needed — extracted on its own.
- Nothing enforces the boundaries yet; they are a convention checked in review. The
  convention was broken once: the audit log sat in `Shared` and referenced
  `Auth\Entity\User`, the actor of a record. It became a module of its own that names the
  actor by id ([0017](0017-audit-log-as-its-own-module.md)). When the next violation
  appears, add an architecture test (Deptrac or PHPat) to CI instead of relying on review.
- The glob import means a module appears by creating a directory — easy to add, and easy to
  miss in review, since no central file changes.
