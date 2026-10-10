# Roadmap

> After Phase 1 (simple CRUD), the goal shifted: stop learning by copy-pasting tutorials
> and start building something with real architecture decisions, business rules, and constraints.

---

## Architecture Vision

Each module is a vertical slice of functionality — self-contained, self-registering, easy to extract into a microservice.

```
src/
├── Auth/                   ← authentication module
│   ├── Controller/
│   ├── Entity/User.php
│   ├── DTO/
│   ├── Repository/
│   ├── Resource/
│   ├── di.php              ← module service registration
│   └── routing.php         ← module routes
│
├── Task/                   ← task module
│   ├── Controller/
│   ├── Entity/Task.php
│   ├── Enum/TaskStatus.php
│   ├── DTO/
│   ├── Repository/
│   ├── Resource/
│   ├── Service/
│   ├── di.php
│   └── routing.php
│
├── Project/                ← future module
│   └── ...
│
└── Story/                  ← Foundry fixtures
    └── di.php
```

Modules are auto-discovered via glob:
```php
// config/services.php
$di->import('../src/**/di.php');

// config/routes.php
$routing->import('../src/**/routing.php');
```

### Evolution

```
Phase 1: Simple CRUD Monolith
    └── Single src/ structure
    └── Basic Task entity + REST API

Phase 2: Feature-based Modular Monolith
    └── Auth + Task + Project modules
    └── CRUD + business rules in Service layer

Phase 3: Business Logic
    └── State Machine (status transitions)
    └── WIP Limit, Deadline, Priority
    └── Symfony Messenger Scheduler (cron for overdue tasks)

Phase 4: Domain Events + Audit Log
    └── Events: TaskStatusChanged, TaskAssigned
    └── Messenger handlers write history

Phase 5: CQRS (where justified)
    └── Command/Handler for complex write operations
    └── Query/Handler for optimised reads
    └── NOT everywhere — only where real complexity exists
```

### Architecture Decisions

Each decision, with the alternatives that lost and what it costs, is an
[architecture decision record](adr/README.md).

---

## V2 — Modular Structure ✅

> Rethought the project structure before adding more features.
> Migrated to feature-based modular monolith — each module owns its slice end-to-end.

- [x] Feature-based modular structure (`src/Task/`, `src/Story/`)
- [x] PHP config instead of YAML (`config/services.php`, `config/routes.php`)
- [x] Modules self-register via `di.php` + `routing.php`

---

## Phase 1 — JWT Authentication ✅

> New module: `src/Auth/`

- [x] Install `lexik/jwt-authentication-bundle`
- [x] `User` entity — `id`, `email`, `password`, `createdAt`
- [x] Migration
- [x] Configure `security.yaml` (JWT firewall, `json_login`, password hasher)
- [x] Generate JWT keypair
- [x] `RegisterDTO` with validation
- [x] `AuthController` — `POST /api/v1/auth/register`
- [x] Login via Symfony `json_login` — `POST /api/v1/auth/login` (no controller needed)
- [x] `UserResource` — response transformer
- [x] `Auth/di.php` + `Auth/routing.php`
- [x] Attach `Task` to `User` (ManyToOne)
- [x] Filter tasks by authenticated user
- [x] `UserFactory` (Foundry) for tests
- [x] Update integration tests (Bearer token)
- [x] Update OpenAPI docs (Bearer token)

---

## Phase 1.5 — Auth Hardening ✅ (reuse detection pending)

> Everything the login flow gained after Phase 1: sessions, revocation and abuse protection.

- [x] Access token moved from `Authorization` header to an HttpOnly cookie
- [x] Refresh tokens — rotated on every use, cookie scoped to `/api/v1/auth`
- [x] Only SHA-256 hashes of refresh tokens are stored
- [x] Logout revokes every refresh session and blocklists the access token (Redis)
- [x] Separate `auth` firewall — a stale `access_token` cookie no longer blocks login/register/refresh/logout
- [x] Login throttling — 5 attempts per minute per email + IP, 25 per IP, answered with `429`
- [x] Throttled logins logged to the `security` channel
- [x] `TRUSTED_PROXIES` so IP-based limits see the real client behind a proxy
- [x] `app:auth:purge-expired-refresh-tokens` command
- [ ] **Refresh token reuse detection (RFC 9700)** — mark tokens used instead of deleting them, group them into families, revoke the whole family when a used token is presented again. Also removes the concurrent-refresh race
- [x] Rate limit `POST /api/v1/auth/register`
- [x] Normalise emails (lowercase) on registration and lookup — an `Email` value object owns the canonical form
- [ ] Identify the user in the JWT by immutable id instead of email

---

## API Error Handling & Observability ✅ (follow-ups pending)

> How the API reports a failure, and how a failure in production is traced back to the request that caused it.

- [x] One error envelope for every failure — validation, domain conflicts, authentication, 500s
- [x] Domain exceptions mapped to statuses in `framework.exceptions`, so modules stay free of HTTP concerns
- [x] `ClientFacingException` marker — only a marked message reaches the client, anything else becomes a neutral status text
- [x] Log level declared per exception class, so an expected `409` no longer flushes the whole production log buffer
- [x] `passthru_level: warning` — a lone warning is written without dumping the buffer
- [x] Errors rendered by a serializer normalizer instead of a `kernel.exception` subscriber, as the Symfony docs recommend
- [x] Request format forced to JSON under `/api/`, so a client that sends no usable `Accept` header is not answered with an HTML page
- [x] Error objects follow the JSON:API specification — `detail`, `source.pointer` for body fields, `source.parameter` for query parameters
- [x] Correlation id: return `X-Request-Id` on every response, reuse an incoming one when it comes from a trusted proxy, and stamp it into every log record via a Monolog processor (needs `expose_headers` in the CORS config)
- [x] Answer `415` instead of a misleading `422` when `Content-Type` is missing — `acceptFormat: 'json'` on the payload mapping
- [x] Add `user_id` to the log processor
- [x] Check whether the password reaches the log context when the buffer is flushed — it does not, a test proves it
- [ ] Granular rate limits on the remaining sensitive endpoints
- [ ] A stable machine-readable `code` on every error object (JSON:API `code` member), so clients branch on the code instead of the status or the text
- [ ] Metrics for authentication failures (failed logins and refreshes), with an alert on a spike — a sign of guessing or leaked tokens
- [ ] ADRs for the decisions taken: a normalizer over a subscriber, JSON:API over Problem Details, `404` on someone else's task, `422` for an invalid query filter

---

## Phase 2 — Workspaces

> New module: `src/Workspace/`. A workspace is the team; projects inside it come later.

- [x] `Workspace` with members and roles — `owner`, `member`, `viewer`, at least one owner ([ADR 0019](adr/0019-workspaces-members-and-roles.md))
- [x] A personal workspace for every user, created at registration
- [x] `/api/v1/workspaces` and `/api/v1/workspaces/{id}/members`
- [x] Symfony Voter — `403` for a member without the right, `404` for a stranger
- [x] `Task` keeps its creator as an id and uses `Auth` only through its contract
- [x] `Task` in a workspace; access by membership, a viewer only reads ([ADR 0020](adr/0020-a-task-belongs-to-a-workspace.md))
- [x] Assign a task to an owner or a member; losing the role takes unfinished tasks off them ([ADR 0021](adr/0021-a-task-has-an-assignee-who-can-work.md))
- [x] Permissions instead of role comparisons; only an owner deletes a task ([ADR 0022](adr/0022-code-asks-for-a-permission.md))
- [ ] Filter tasks by assignee, including nobody; a list of the tasks assigned to me
- [ ] Projects inside a workspace

---

## Notifications

> Telling people what happened to their work, without slowing the request or touching consistency.

- [x] `Task\Contract\TaskAssigned`, published after the commit on an event bus ([ADR 0023](adr/0023-facts-announced-after-the-commit.md))
- [ ] Email to the assignee, through the queue and Mailer — not to the one who assigned, not to someone who has since lost access
- [ ] A notification recorded once, so a message delivered twice does not send two emails
- [ ] In-app inbox — `GET /api/v1/notifications`, mark as read
- [ ] Transactional outbox from Symfony 8.2, so an announcement cannot be lost

---

## Phase 3 — Business Logic

> Real rules enforced in the Service layer — not just database operations.

- [x] **State Machine** — allowed transitions only ([ADR 0015](adr/0015-task-lifecycle-as-explicit-transitions.md)):
  ```
  TODO → IN_PROGRESS → IN_REVIEW → COMPLETED
   ↓         ↓              ↓
  CANCELLED CANCELLED   CANCELLED
  ```
- [ ] **WIP Limit** — max N tasks `IN_PROGRESS` per project
- [ ] **Deadline** — `dueDate` field, cannot be set in the past
- [ ] **Overdue** — Symfony Messenger Scheduler marks tasks as overdue
- [ ] **Priority** — `LOW` / `MEDIUM` / `HIGH` / `CRITICAL`
- [ ] Cannot close a project with open `CRITICAL` tasks

---

## Phase 4 — Audit Log

> Full history of task changes via Domain Events.
> Implemented as `AuditLog` + synchronous event listeners for now.

- [x] `AuditLog` entity
- [x] Domain Events: `TaskCreated`, `TaskUpdated`, `TaskDeleted`
- [x] Symfony EventDispatcher listeners write audit log
- [x] `GET /api/v1/tasks/{id}/audit-logs`

---

## Phase 5 — CQRS (where justified)

> Command/Query pattern — applied selectively, only where business complexity justifies it (Phase 3+).
> Not everywhere.

```
src/Task/
├── Command/
│   ├── CreateTask/
│   │   ├── CreateTaskCommand.php
│   │   └── CreateTaskHandler.php      ← WIP Limit check
│   └── TransitionStatus/
│       ├── TransitionStatusCommand.php
│       └── TransitionStatusHandler.php   ← State Machine
├── Query/
│   ├── GetAllTasks/
│   └── GetTask/
└── Event/
    └── TaskStatusChanged.php
```
