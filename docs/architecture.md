# Architecture

Four views of the application. Each one links the decision record that explains why it
looks this way.

## Containers

```mermaid
flowchart LR
    client([API client])
    nginx[nginx]
    app[php-fpm<br/>Symfony 8.1]
    db[(PostgreSQL 18)]
    redis[(Redis 7)]

    client -->|HTTP, cookies| nginx
    nginx -->|FastCGI| app
    app -->|tasks, users, refresh tokens,<br/>audit log, full-text search| db
    app -->|JWT blocklist, rate-limit counters| redis
```

One deployable application and one database. PostgreSQL also serves the search
([0013](adr/0013-task-search-postgresql-full-text.md)). Redis holds short-lived data: the
blocklist of logged-out access tokens, read on every authenticated request, and the
rate-limit counters.

## Modules

```mermaid
flowchart TB
    subgraph Task
        direction TB
        t1[Controller · DTO · Resource]
        t2[Service]
        t3[Entity · Enum · ValueObject]
        t4[Repository · Query specifications]
        t5[Event · Listener]
    end

    subgraph Auth
        direction TB
        a1[Controller · DTO · Resource]
        a2[Service · RefreshToken]
        a3[Entity · ValueObject]
        a4[Security]
    end

    subgraph Workspace
        direction TB
        w1[Controller · DTO · Resource]
        w2[Service · Listener]
        w3[Entity · Contract]
        w4[Security]
    end

    subgraph AuditLog
        direction TB
        l1[Entity · Repository · Resource]
        l2[Service]
    end

    subgraph Shared
        direction TB
        s1[Http — error rendering, request id]
        s2[Api — JSON:API responses]
        s4[Persistence · Query]
    end

    Task -->|contract only| Auth
    Task -->|contract only| Workspace
    Task -->|writes and reads its history| AuditLog
    Workspace -->|contract only| Auth
    Workspace -->|writes its history| AuditLog
    Task --> Shared
    Workspace --> Shared
    Auth --> Shared
    AuditLog --> Shared
```

An arrow reads "depends on". A module owns its slice end to end and registers itself through its own `di.php` and
`routing.php` ([0001](adr/0001-feature-based-modular-monolith.md)). `Shared` depends on no
module, and the audit log on none of the modules that write to it: a record names its
actor by id ([0017](adr/0017-audit-log-as-its-own-module.md)). The allowed
dependencies are declared in `deptrac.php` and checked in CI. `Task` and `Workspace` use
only `Auth`'s contract — an interface for the current user, a lookup by email and the
registration event — and `Task` only `Workspace`'s: a member's role and a reference to
the workspace ([0018](adr/0018-modules-meet-through-contracts.md)).

## Authentication

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant A as API
    participant DB as PostgreSQL
    participant R as Redis

    Note over C,R: Login
    C->>A: POST /auth/login (email, password)
    A->>DB: verify the password, store the hash of a new refresh token
    A-->>C: 204 + cookies: access_token (15 min), refresh_token (30 days)

    Note over C,R: A request
    C->>A: GET /tasks/{id} (access_token cookie)
    A->>R: is this token blocklisted?
    A-->>C: 200

    Note over C,R: The access token expired
    C->>A: POST /auth/refresh (refresh_token cookie)
    A->>DB: find the token by its hash, delete it, store a new one
    A-->>C: 204 + new access_token + new refresh_token

    Note over C,R: Logout
    C->>A: POST /auth/logout
    A->>DB: delete every refresh token of the user
    A->>R: blocklist the access token until it expires
    A-->>C: 204 + cleared cookies
```

Both tokens live in HttpOnly cookies ([0004](adr/0004-access-token-in-httponly-cookie.md));
the refresh cookie is sent only to `/api/v1/auth`.
The access token is short-lived and stateless; the refresh token is stored only as a
SHA-256 hash and rotated on every use, which is what makes a session revocable
([0005](adr/0005-revocable-sessions.md)). The auth endpoints sit behind their own firewall,
so a stale access token cannot block a login or a refresh.

## Task lifecycle

```mermaid
stateDiagram-v2
    [*] --> todo: POST /workspaces/{id}/tasks
    todo --> in_progress: start
    in_progress --> in_review: submit-for-review
    in_progress --> blocked: block (reason)
    blocked --> in_progress: unblock
    in_review --> completed: complete
    todo --> cancelled: cancel (reason)
    in_progress --> cancelled: cancel (reason)
    blocked --> cancelled: cancel (reason)
    in_review --> cancelled: cancel (reason)
    completed --> [*]
    cancelled --> [*]
```

`completed` and `cancelled` are final: nothing leads out of them, and the circle at the
bottom marks the end of a task's life, not a status. The table lives in `TaskTransition`, and the `Task` entity enforces it: it has no status
setter, only `start()`, `submitForReview()`, `complete()`, `block()`, `unblock()` and
`cancel()` ([0015](adr/0015-task-lifecycle-as-explicit-transitions.md),
[0016](adr/0016-blocked-is-a-status-of-work-in-progress.md)). A task's response links the
transitions its status allows, so a client knows which actions to offer.

### What one transition does

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant Ctl as Controller
    participant S as CancelTask
    participant T as Task
    participant L as Audit log listener
    participant DB as PostgreSQL

    C->>Ctl: POST /tasks/{id}/cancel {"reason": "…"}
    Ctl->>DB: load the task and the caller's role in its workspace
    Note right of Ctl: not found or not a member → 404
    Ctl->>Ctl: ask the voter, validate the body
    Note right of Ctl: a viewer → 403, no usable reason → 422
    Ctl->>S: handle(task, reason, actor id)
    activate S
    Note over S,DB: one transaction
    S->>T: cancel(reason, now)
    Note right of T: transition not in the table → 409
    T->>T: add the status history row, change the status
    S->>L: TaskStatusChanged
    L->>L: persist the audit entries
    S->>DB: one flush: UPDATE tasks, INSERT history, INSERT audit log
    deactivate S
    Ctl-->>C: 200 + the task
```

The order of the first two steps is deliberate: an invalid body sent to a task of a
workspace the caller is not in answers `404`, not a `422` that would confirm the task
exists ([0011](adr/0011-foreign-task-answers-404.md),
[0020](adr/0020-a-task-belongs-to-a-workspace.md)). The audit entries are written in the same
transaction as the change, so the history cannot disagree with the data
([0012](adr/0012-synchronous-audit-log-one-transaction.md)).
