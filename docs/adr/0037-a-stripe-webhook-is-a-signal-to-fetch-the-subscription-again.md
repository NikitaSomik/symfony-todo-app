# 0037. A Stripe webhook is a signal: one consumer fetches the subscription again and is the only one to write it

- Status: proposed
- Date: 2026-11-16
- Implemented in: #139, #140

## Context

[0031](0031-billing-on-stripe-by-plan-tiers.md) settled that Stripe decides a
subscription's status and we keep a copy, refreshed on every webhook, and sketched how:
an inbox table, a worker that locks the subscription before asking Stripe, no call to
Stripe inside a transaction. Building it raised what the sketch left open, and showed that
two of its points cannot hold together: a row lock lives only inside a transaction, so
"lock before asking Stripe" and "no call to Stripe in a transaction" exclude each other.

What we know of Stripe's delivery, from its documentation:

- events arrive late, more than once, and "Stripe doesn't guarantee the delivery of events
  in the order that they're generated"; `created` must not be used to order them;
- a paid Checkout sends several events about one subscription within a second
  (`checkout.session.completed`, `customer.subscription.created`, `…updated`);
- the body is a snapshot that "can be stale by the time your application processes it",
  and Stripe recommends fetching the object from the API before acting on it;
- an endpoint must answer `2xx` quickly and leave the work to a queue; Stripe retries a
  failed delivery for up to three days and keeps events for thirty.

What others do: Laravel Cashier, Discourse and Theo Browne's widely shared guide keep no
table of events and make handlers that only update; spatie's webhook client, Lago and
dj-stripe store every event, because their handlers do what must not happen twice (mark
an invoice paid, write history). Twenty hit the race of two parallel handlers in
production in 2026: a workspace stayed suspended under an active subscription, and
nothing corrected it. Cashier and Pay keep one row per subscription, so a customer's
cancelled subscriptions stay beside the current one.

Today `subscriptions` holds one row per workspace, its plan, and nothing writes it: a
workspace that pays keeps Free.

Our handler writes one thing, the copy of the subscription, and sends nothing.

## Decision

### The endpoint

- **`POST /webhooks/stripe`, our own controller, outside `/api`.** No firewall and no
  `access_control` rule matches the path, so the signature is its only guard. It is
  checked with `Stripe\Webhook::constructEvent` on the raw body and
  `STRIPE_WEBHOOK_SECRET`, with Stripe's tolerance of 300 seconds.
- **A refused signature answers `400` without details.** The `billing` log gets a warning
  with the reason (no header, outside the tolerance, no match), the IP, the user agent and
  the size of the body, never the body or the headers: when our secret is wrong the body is
  a real customer's data, and otherwise it is whatever a stranger chose to send. Stripe's Workbench shows the body and our answer of
  every delivery.
- **An empty `STRIPE_WEBHOOK_SECRET` answers `500`** without details and writes a
  `critical` to `billing`. The fault is our configuration, not the sender's, so it does not
  hide among refused signatures, and Stripe keeps delivering until the secret is set.
- **The endpoint takes only the subscription's id from the event** —
  `data.object.subscription` of `checkout.session.completed`, `data.object.id` of
  `customer.subscription.created`, `…updated` and `…deleted` — dispatches
  `RefreshSubscription` with it and answers `200`. Any other type answers `200` and is
  dropped.
- **A message that cannot be queued is not caught.** Messenger's `TransportException`
  reaches the framework, which answers `500` and logs it, and Stripe delivers again: it
  retries on any answer that is not `2xx`.

### The worker

- **`RefreshSubscriptionHandler` fetches the subscription from Stripe by its id** and
  writes what Stripe answers: the plan from its price's lookup key and the status mapped as
  0031 says. The workspace is the one whose payer is the subscription's customer, found in
  `payers` by `stripe_customer_id`, as Laravel Cashier finds the billable by its Stripe id.
  The body of the event plays no part, so an event late, twice or out of order leads to the
  same write.
- **One row per Stripe subscription.** `stripe_subscription_id` is its key; a partial
  unique index on `workspace_id` allows one subscription that is not `canceled` per
  workspace, and serves every read of a workspace's plan. A cancelled subscription keeps
  its row with the status `canceled`, and a new one after it gets a row of its own.
  `updated_at` is the time of the last refresh.
- **It is the only writer of the copy.** A page a customer returns to after paying reads
  the status; it does not write it.
- **`RefreshSubscription` travels on a transport of its own, `stripe_events`, with exactly
  one consumer.** Two events about one subscription are never handled at the same time,
  so the newest fetch is always the last write, without a lock. Other messages stay on
  `async`, which can run as many consumers as needed. The bus that carries it pings the
  database connection, as `event.bus` does.
- **A failed call to Stripe is retried three times, after 30 seconds, 2 minutes and
  8 minutes**, then the message goes to `failed`. A listener of Messenger's
  `WorkerMessageFailedEvent` writes a `RefreshSubscription` that will not be retried to the
  `billing` log as an `error`, with the subscription's id.
- **A subscription whose customer is not in `payers`** is logged as an error and not
  written, without retries. Our Checkout opens every page for the payer's customer, so in
  production it does not happen; the test account still delivers subscriptions we did not
  create, such as fixtures of `stripe trigger` or those of another environment that shares
  the account.
- **A second subscription for a workspace whose subscription is not `canceled`** is not
  written; the `billing` log gets an error with both ids
  ([0033](0033-a-workspace-has-one-open-checkout-page.md)), and the partial unique index
  refuses it if the check is ever bypassed.

### What the copy grants

- **A workspace's plan is the plan of its subscription that is not `canceled`, and only
  while that subscription is `active` or in `grace`.** `pending` (a payment Stripe still
  waits for, up to 23 hours) and `canceled` grant Free, so a plan is had only once it is
  paid. With no such subscription the plan is Free.
- **`GET /api/v1/workspaces/{id}/billing` answers the plan and the status from the copy**
  as a JSON:API resource `billing`, to every member with `view`; a workspace outside the
  user's reach answers `404`. An owner returning from Checkout polls it until the payment
  shows; members see the status that 0031's banner for `grace` shows them anyway.

## Alternatives considered

- **Taking the state from the event's body.** No call to Stripe and no dependency on it
  being up. But a retried `incomplete` arriving after `active` writes the old status over
  the new one, and nothing corrects it until a reconciliation. Guarding by the event's time
  does not help: `created` counts whole seconds, and the events of one payment share the
  same second.
- **An inbox table of events** (0031), deduplicating by the event's id. It protects work
  that must not run twice; ours writes the same copy each time, Messenger already keeps
  retries and failed messages, and Stripe keeps thirty days of events. Signal: the first
  handler that does something that must not repeat, such as the emails of failed
  payments.
- **A lock per workspace** with `symfony/lock` on PostgreSQL advisory locks, as Twenty did
  after its incident. Correct with any number of consumers, for a new package and a lock to
  manage while Stripe is called. One consumer gives the same order for free while the load
  allows it. Signal: a message waits on `stripe_events` longer than 30 seconds, or a
  second consumer is needed.
- **Symfony's Webhook component.** The framework's tool for receiving webhooks, but it has
  no parser for Stripe, answers `406` to an event its parser returns nothing for, which
  Stripe would retry for three days, routes every webhook through one message class, and
  its open issues about these points have gone unanswered. Our controller is about thirty
  lines.
- **`/api/v1/webhooks/stripe`.** It would sit with the rest of the API, but `^/api` asks
  for a JWT, so it needs a firewall of its own and a public `access_control` rule, each
  placed before the API's, and a mistake in that order answers Stripe `401`. Under
  `/api/v1` it would also land in the clients' OpenAPI, follow JSON:API (0008) and carry
  our API's version, while its format is set by Stripe's API version.
- **Refusing to start without `STRIPE_WEBHOOK_SECRET`.** The mistake shows before the
  first event, but every environment then needs the secret, a developer's who never
  receives webhooks included; `STRIPE_SECRET_KEY` is not required at start either.
- **A row lock with the transaction kept open across the call to Stripe.** It keeps a
  database connection for as long as Stripe takes, up to the 30-second timeout and its
  retry, against the rule of 0031.
- **The workspace from the subscription's metadata.** Our Checkout sets `workspace_id`
  there too, but metadata is a free field anyone with the account can edit, and the id
  still has to be checked against Workspace through a contract of its own. `payers`
  already pairs each customer with its workspace in our database.
- **One row per workspace, overwritten by its next subscription.** Simpler to read, but the
  cancelled subscription and its Stripe id are lost.
- **A plan during `pending`.** The customer gets the plan the moment the page closes, and
  keeps it for up to 23 hours without paying, again with each new page.
- **`503` from an exception of our own when the queue is down.** The right status, for a
  class and a line of configuration; Stripe acts the same on `500`. Signal: the logs need to
  tell a down queue from a bug.
- **Five retries over twenty minutes.** It rides out longer outages of Stripe, and keeps a
  paid customer waiting as long before the failure is seen.
- **A history of statuses of our own**, in a table like `subscription_status_changes` or in
  the audit log. Stripe keeps the subscription's dates and thirty days of events. Signals:
  a report on status changes (a table), or an owner who needs to see the history of
  payments in the application (the audit log).
- **`GET …/billing` for `manage_billing` only.** It hides a status that the banner for
  `grace` shows every member anyway.

## Consequences

- Every event costs one call to Stripe, and a Stripe that is down delays the copy until it
  answers.
- One consumer handles about 2.5 events a second at 0.4 seconds a call. A purchase is three
  or four events, so more than about 35 purchases a minute make the queue grow: access
  opens late, nothing is lost. A slow Stripe holds the queue for up to a minute per event.
- `stripe_events` must run with exactly one consumer. A second one, added for speed,
  brings back the race this record avoids.
- The nightly reconciliation of 0031 is not built yet. Until it is, a message in `failed`
  is the only trace of a customer who paid and did not get the plan; someone has to read the
  `billing` errors.
- `planOf` follows the status, so a status change moves every limit that asks it: members,
  team workspaces on Free and meetings ([0036](0036-meet-asks-the-monolith-for-permissions-and-limits.md)).
- `active` means paid only for payment methods confirmed at once: card, Apple Pay and Link,
  the ones switched on. A method confirmed days later, such as SEPA Direct Debit, makes a
  subscription `active` before the money arrives; before one is switched on, the handler
  learns to wait for the first invoice to be paid.
- The access a status grants is decided here for `pending`; `suspended` comes with failed
  payments.
