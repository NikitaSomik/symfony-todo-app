# symfony-todo-app

![CI](https://github.com/NikitaSomik/symfony-todo-app/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)

A task API built as a modular Symfony monolith. The domain is small on purpose: the
project is about the decisions behind it. Each one is written down with the alternatives
that lost and what the choice costs, and the ones that were later revised are kept.

## What the application does

A task tracker. Today it serves one person; the next release turns it into a tool for a
small team.

The rules it enforces now:

- **A task belongs to its owner.** Nobody else can read it, change it, or learn that it
  exists ([0011](docs/adr/0011-foreign-task-answers-404.md)).
- **Work moves one way:** to do → in progress → in review → completed. Finished work stays
  finished — a completed or cancelled task cannot be reopened
  ([0015](docs/adr/0015-task-lifecycle-as-explicit-transitions.md)).
- **Cancelling takes an explanation.** A task cannot be dropped silently; the reason stays
  with it.
- **Stuck work is visible.** A task in progress can be blocked, with a reason, and it does
  not move forward until it is unblocked
  ([0016](docs/adr/0016-blocked-is-a-status-of-work-in-progress.md)).
- **Every change leaves a record:** who changed what, from which value to which, and when
  the status moved. The record is written together with the change, so it cannot be
  missing ([0012](docs/adr/0012-synchronous-audit-log-one-transaction.md)).
- **A task is found by what it says.** Search covers the title and the description and
  puts the best match first ([0013](docs/adr/0013-task-search-postgresql-full-text.md)).
- **A session can be ended.** Logging out on one device ends the sessions on all of them,
  within 15 minutes at most ([0005](docs/adr/0005-revocable-sessions.md)).

What comes next ([roadmap](docs/roadmap.md)): shared work — members with roles, so that
several people work on the same tasks and a task has an assignee; then limits on work in
progress and deadlines with consequences.

## Where to look

| Question | Answer | Record |
|---|---|---|
| How is the code organised? | Modules that own their slice end to end and register themselves | [0001](docs/adr/0001-feature-based-modular-monolith.md) |
| How does a browser stay logged in? | Short access token and rotating refresh token, both in HttpOnly cookies; logout revokes the session | [0004](docs/adr/0004-access-token-in-httponly-cookie.md), [0005](docs/adr/0005-revocable-sessions.md) |
| How does a failure reach the client? | Exceptions mapped to statuses in configuration, rendered by a serializer normalizer — after a first design that did not hold | [0006](docs/adr/0006-errors-rendered-by-exception-subscriber.md) → [0007](docs/adr/0007-exception-mapping-and-error-normalizer.md) |
| How far does the API follow JSON:API? | Responses and query parameters do, request bodies stay plain JSON — full compliance was built on a branch and measured | [0008](docs/adr/0008-json-api-responses-plain-json-requests.md) |
| What is logged? | Per request: buffered quietly, written on failure, tagged with request and user ids | [0009](docs/adr/0009-production-logging.md) |
| What does someone else's task answer? | `404`, exactly like a missing one | [0011](docs/adr/0011-foreign-task-answers-404.md) |
| Can the audit log disagree with the data? | No: it is written in the same transaction, one flush per use case | [0012](docs/adr/0012-synchronous-audit-log-one-transaction.md) |
| Why a stored `tsvector` column for search? | Because results are ranked — measured against an expression index on a million tasks | [0013](docs/adr/0013-task-search-postgresql-full-text.md) |
| Who decides which status may follow which? | The `Task` entity; Symfony Workflow was built on a branch and compared | [0014](docs/adr/0014-task-status-changed-as-part-of-an-update.md) → [0015](docs/adr/0015-task-lifecycle-as-explicit-transitions.md) |
| Is a blocked task a status or a flag? | A status of work in progress, as most teams use it; Jira's flag and task links were weighed | [0016](docs/adr/0016-blocked-is-a-status-of-work-in-progress.md) |

All records: [docs/adr](docs/adr/README.md). Diagrams of the containers, the modules, the
authentication flow and a task transition: [docs/architecture.md](docs/architecture.md).

## Modules

```mermaid
flowchart LR
    Task -->|owner of a task| Auth
    Task --> Shared
    Auth --> Shared
    Shared -.->|actor of an audit record| Auth
```

`Auth` — registration, login, refresh, logout. `Task` — tasks, their lifecycle, search and
history. `Shared` — what both use: error rendering, JSON:API responses, the audit log. The
dotted arrow is a known violation of "Shared depends on no module", recorded in
[0001](docs/adr/0001-feature-based-modular-monolith.md).

## Task lifecycle

```mermaid
stateDiagram-v2
    [*] --> todo
    todo --> in_progress: start
    in_progress --> in_review: submit-for-review
    in_progress --> blocked: block
    blocked --> in_progress: unblock
    in_review --> completed: complete
    todo --> cancelled: cancel
    in_progress --> cancelled: cancel
    blocked --> cancelled: cancel
    in_review --> cancelled: cancel
    completed --> [*]
    cancelled --> [*]
```

A transition is its own endpoint (`POST /api/v1/tasks/{id}/start`), a refused one answers
`409`, and each one writes the status history and the audit log in one transaction.

## Deliberately not built

| What | Why not | What would change that |
|---|---|---|
| API Platform | The goal is to build the API layer on Symfony itself and understand it | — |
| An identity provider (Keycloak, Auth0, SSO) | Deferred, not rejected: this stage is about building the mechanism ([0002](docs/adr/0002-authentication-inside-the-application.md)) | SSO, MFA, or third-party applications acting on a user's behalf |
| Full JSON:API compliance | About 450 lines of custom infrastructure around Symfony's standard tools, for a CRUD model the project is moving away from; kept on `feature/api-json-api-compliance` | Strict compliance becoming a goal |
| Symfony Workflow | Same amount of code, but the entity can no longer protect its own status; kept on `feature/task-lifecycle-workflow` | Several transition rules contributed by different modules |
| A search engine | Two columns of one table: PostgreSQL full-text search is enough | Typo tolerance, or ranking that takes too long |
| A message bus | Nothing runs asynchronously yet, and the audit log is synchronous on purpose | A side effect that is slow or calls another system |
| CQRS everywhere, a separate read store, event sourcing | Command and query handlers are planned only where real complexity justifies them ([roadmap](docs/roadmap.md), Phase 5) | A read model the entity cannot serve |

## Quality gates

Every pull request that changes code runs, against a real PostgreSQL:

- PHP-CS-Fixer and PHPStan level 8
- `lint:container` for the development and production containers
- migrations, then `doctrine:schema:validate`
- PHPUnit — application tests through HTTP, unit tests for the domain rules

## Stack

PHP 8.5 · Symfony 8.1 · Doctrine ORM · PostgreSQL 18 · Redis · Docker Compose

## Quick start

```sh
echo "JWT_SECRET=$(openssl rand -hex 32)" >> .env.local
make build
make up
make migrate
```

The OpenAPI description is in [docs/openapi.json](docs/openapi.json); `make generate-openapi`
regenerates it.

## Commands

```sh
make check-code      # code style, static analysis, tests
make run-tests
make run-phpstan
make run-cs
make db-reset        # drop all tables and run the migrations
make                 # the full list
```

## Roadmap

What comes next and what is done: [docs/roadmap.md](docs/roadmap.md).
