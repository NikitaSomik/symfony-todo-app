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

Phase 2: Feature-based Modular Monolith      ← current
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

| Decision | Choice | Reason |
|---|---|---|
| Structure | Feature-based Modular Monolith | Vertical slicing, easy to extract to microservice |
| Config format | PHP (di.php, routing.php) | IDE refactoring support, auto-discovery via glob |
| API style | REST JSON | Simple, no overhead |
| Auth | JWT stateless (lexik) | API-friendly, no sessions |
| Docs | NelmioApiDocBundle | Native Symfony, full control, no API Platform magic |
| Testing | PHPUnit + Foundry | Integration tests against real DB |
| CQRS | Symfony Messenger | Only where business logic justifies it (Phase 3+) |
| No Twig | — | Pure API, no HTML rendering |
| No API Platform | — | Learning Symfony internals, full control |

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
- [ ] Rate limit `POST /api/v1/auth/register`
- [ ] Normalise emails (lowercase) on registration and lookup
- [ ] Identify the user in the JWT by immutable id instead of email

---

## Phase 2 — Projects

> New module: `src/Project/`

- [ ] `Project` entity — `id`, `name`, `description`, `owner`, `createdAt`
- [ ] `ProjectMember` — User ↔ Project with role (`OWNER`, `MEMBER`)
- [ ] Move `Task` under `Project`
- [ ] `ProjectController` — CRUD `/api/v1/projects`
- [ ] Symfony Voters — access control (only members can see/edit)
- [ ] Assign task to project member

---

## Phase 3 — Business Logic

> Real rules enforced in the Service layer — not just database operations.

- [ ] **State Machine** — allowed transitions only:
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
