# Architecture

The views follow the [C4 model](https://c4model.com), from the outside in: the system in its
world, the processes it runs as, the modules inside them, then the flows and states that
matter. Each one links the decision record that explains why it looks this way.

## System context

```mermaid
flowchart LR
    member([Workspace member])
    client[API client<br/><i>a web or mobile app,<br/>not in this repository</i>]
    app[[Todo app API<br/><i>workspaces, members, tasks,<br/>plans and their limits</i>]]
    mail[Mail server<br/><i>SMTP</i>]
    hibp[Have I Been Pwned<br/><i>Pwned Passwords range API</i>]
    stripe[Stripe<br/><i>customers, subscriptions,<br/>Checkout pages</i>]

    member -->|uses| client
    client -->|JSON:API over HTTPS| app
    app -->|an email to a new assignee| mail
    mail -.->|delivers| member
    app -->|5 characters of a new<br/>password's SHA-1| hibp
    app -->|a workspace's customer,<br/>its subscriptions, a payment page| stripe
    member -.->|pays on Stripe's<br/>Checkout page| stripe
```

People reach the API only through a client. Three systems outside it get a call: the mail
server, from the worker, when a task gets an assignee
([0024](adr/0024-the-assignee-is-told-by-email-from-the-worker.md)), and Have I Been Pwned,
when someone signs up; if it does not answer, the password is accepted
([0026](adr/0026-new-passwords-checked-against-known-breaches.md)). Stripe takes the
payments: an owner who buys a plan is sent to Stripe's Checkout page, and the card never
reaches the API ([0031](adr/0031-billing-on-stripe-by-plan-tiers.md),
[0032](adr/0032-a-workspace-buys-a-plan-through-stripe-checkout.md)).

## Containers

```mermaid
flowchart LR
    client([API client])

    subgraph system [Todo app API]
        nginx[nginx]
        app[php-fpm<br/>Symfony 8.1]
        worker[worker<br/>messenger:consume async]
        db[(PostgreSQL 18)]
        redis[(Redis 7)]
        mq[[RabbitMQ 4]]
    end

    mail[Mail server]
    stripe[Stripe API]

    client -->|HTTP, cookies| nginx
    nginx -->|FastCGI| app
    app -->|tasks, users, refresh tokens,<br/>audit log, full-text search,<br/>workspaces' Stripe customers| db
    app -->|JWT blocklist, rate-limit counters,<br/>Stripe price ids, query results<br/>in production| redis
    app -->|events, after the commit| mq
    mq -->|async queue| worker
    worker -->|reads the task and the email;<br/>keeps messages that still fail| db
    worker -->|SMTP| mail
    app -->|HTTPS: 3 s to connect,<br/>30 s an attempt, one retry| stripe
```

One codebase runs as two processes: php-fpm answers requests, and the worker handles what
the request does not wait for. A module announces a fact only after its transaction commits
([0023](adr/0023-facts-announced-after-the-commit.md)); the facts with a reaction in the
worker go to RabbitMQ, today only `TaskAssigned`. A message that still fails after its
retries is kept in PostgreSQL.

One database. PostgreSQL also serves the search
([0013](adr/0013-task-search-postgresql-full-text.md)). Redis holds short-lived data: the
blocklist of logged-out access tokens, read on every authenticated request, the rate-limit
counters, the id of each plan's Stripe price for an hour, and in production Doctrine's
cache of query results. In development, Mailpit stands in for the mail server.

php-fpm calls Stripe during the request that opens a payment page, outside any transaction.
Stripe's client waits `STRIPE_TIMEOUT_SECONDS` (30) an attempt with one retry, and nginx
waits 75 seconds for PHP, longer than two attempts, so a call Stripe does not answer
reaches the client as the API's `503` with `Retry-After`, not as nginx's `504`. The
guarantee is per call, not per request: a Stripe slow on several calls in a row can still
end in nginx's `504` ([0032](adr/0032-a-workspace-buys-a-plan-through-stripe-checkout.md)). Of a paying workspace,
PostgreSQL keeps only its Stripe customer's id (`payers`); the invoices stay in Stripe.

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

    subgraph Notification
        direction TB
        n1[Handler]
    end

    subgraph Billing
        direction TB
        b1[Controller · DTO · Security]
        b2[Service · Exception]
        b3[Entity · Enum · Repository]
        b4[Payment port · Stripe adapter]
    end

    subgraph Shared
        direction TB
        s1[Http — error rendering, request id, Retry-After]
        s2[Api — JSON:API responses]
        s3[Messaging — EventPublisher · Logging]
        s4[Persistence · Query]
    end

    Task -->|contract only| Auth
    Task -->|contract only| Workspace
    Task -->|writes and reads its history| AuditLog
    Workspace -->|contract only| Auth
    Workspace -->|writes its history| AuditLog
    Notification -->|contract only| Task
    Notification -->|contract only| Auth
    Billing -->|contract only; implements<br/>its plan limits| Workspace
    Billing -->|contract only| Auth
    Task --> Shared
    Workspace --> Shared
    Auth --> Shared
    AuditLog --> Shared
    Billing --> Shared
```

An arrow reads "depends on". A module owns its slice end to end and registers itself through its own `di.php` and
`routing.php` ([0001](adr/0001-feature-based-modular-monolith.md)). `Shared` depends on no
module, and the audit log on none of the modules that write to it: a record names its
actor by id ([0017](adr/0017-audit-log-as-its-own-module.md)). The allowed
dependencies are declared in `deptrac.php` and checked in CI. `Task` and `Workspace` use
only `Auth`'s contract — an interface for the current user, a lookup by email and the
registration event — and `Task` only `Workspace`'s: a member's role and a reference to
the workspace ([0018](adr/0018-modules-meet-through-contracts.md)). `Notification` reads tasks
and email addresses through contracts and reacts to `Task`'s events in the worker
([0024](adr/0024-the-assignee-is-told-by-email-from-the-worker.md)). `Workspace` asks what a
plan allows through a contract of its own, and `Billing` answers it, so `Workspace` knows
nothing of plans ([0031](adr/0031-billing-on-stripe-by-plan-tiers.md)). `Billing` reads the
buyer's id and email from `Auth`'s contract, asks `Workspace`'s whether a member may manage
billing, and reaches Stripe only through its own `PaymentProvider` port, which
`StripePaymentProvider` implements.

## Reacting to events

Three kinds of class react to an event, and each is named and placed by what it reacts to.

| Reacts to | Runs | Named | Lives in | Example |
|---|---|---|---|---|
| An event of the framework — a request, a response, a login, a JWT | in the request | after what it hooks into, with `Listener` | `EventListener/`, or the folder of its concern in `Shared` | `LogoutListener`, `Shared/Http/RequestIdListener` |
| An event of the application, through the event dispatcher | in the request, in its transaction | after what it does, as a verb | `Listener/`, one class per event | `Task/Listener/AuditLog/LogUpdated`, `Task/Listener/Workspace/UnassignTasksOfMember` |
| An event of the application, through `event.bus` | in the worker, after the commit | after what it does, as a verb | `Handler/`, one class per event | `Notification/Handler/Task/SendTaskAssignedEmail` |

Under `Listener/` and `Handler/`, a reaction to another module's event sits in a folder named
after that module (`Listener/Workspace/`, `Handler/Task/`), and a reaction to the module's own
events in a folder named after its purpose (`Listener/AuditLog/`). A verb names what happens,
so a second reaction to the same event gets a name of its own; a class named after the event
(`TaskAssignedHandler`) would not leave room for it.

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
    A->>DB: verify the password, start a token family, store the hash of its first token
    A-->>C: 204 + cookies: access_token (15 min), refresh_token (30 days)

    Note over C,R: A request
    C->>A: GET /tasks/{id} (access_token cookie)
    A->>R: is this token blocklisted?
    A-->>C: 200

    Note over C,R: The access token expired
    C->>A: POST /auth/refresh (refresh_token cookie)
    A->>DB: lock the token and its family, mark the token used, store a new one in the family
    A-->>C: 204 + new access_token + new refresh_token

    Note over C,R: A spent token presented again, past the reuse window
    C->>A: POST /auth/refresh (a copied refresh_token)
    A->>DB: revoke the family
    A-->>C: 401, and a warning in the security log

    Note over C,R: Logout
    C->>A: POST /auth/logout
    A->>DB: revoke the family of the presented refresh token
    A->>R: blocklist the access token until it expires
    A-->>C: 204 + cleared cookies
```

Both tokens live in HttpOnly cookies ([0004](adr/0004-access-token-in-httponly-cookie.md));
the refresh cookie is sent only to `/api/v1/auth`.
The access token is short-lived, stateless and signed with RS256: the API's private key signs
it, and the public key is enough to verify it ([0035](adr/0035-access-tokens-signed-with-rs256.md)). The refresh token is stored only as a
SHA-256 hash and rotated on every use, which is what makes a session revocable
([0005](adr/0005-revocable-sessions.md)). The tokens rotated from one sign-in form a family;
a spent one presented again revokes it ([0025](adr/0025-refresh-token-families-and-reuse-detection.md)). The auth endpoints sit behind their own firewall,
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
    in_review --> in_progress: request-changes
    todo --> cancelled: cancel (reason)
    in_progress --> cancelled: cancel (reason)
    blocked --> cancelled: cancel (reason)
    in_review --> cancelled: cancel (reason)
    completed --> [*]
    cancelled --> [*]
```

`completed` and `cancelled` are final: nothing leads out of them, and the circle at the
bottom marks the end of a task's life, not a status. The table lives in `TaskTransition`, and the `Task` entity enforces it: it has no status
setter, only `start()`, `submitForReview()`, `complete()`, `requestChanges()`, `block()`, `unblock()` and
`cancel()` ([0015](adr/0015-task-lifecycle-as-explicit-transitions.md),
[0016](adr/0016-blocked-is-a-status-of-work-in-progress.md),
[0030](adr/0030-a-task-in-review-goes-back-to-work.md)). A task's response links the
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
