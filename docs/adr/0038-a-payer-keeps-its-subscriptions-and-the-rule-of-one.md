# 0038. A payer is the aggregate of its subscriptions: it syncs them from Stripe and keeps the rule of one

- Status: accepted
- Date: 2026-11-18
- Implemented in: #PR

## Context

[0037](0037-a-stripe-webhook-is-a-signal-to-fetch-the-subscription-again.md) settled what
the worker does: fetch the subscription from Stripe by its id and write the copy from the
answer, one subscription per row, at most one that is not `canceled` per workspace. #139
built it as one handler that held all of it:

- it decided whether the subscription was new or known, and either updated the row
  (`Subscription::refresh()`) or created one (`saveNewOrLogRefusal()`);
- it held the domain rule "a workspace has one subscription that is not `canceled`" as a
  private method that logs, so the rule could be tested only through Messenger, the
  database and a fake Stripe;
- the entity had no behaviour but a setter of plan, status and time named `refresh`, a
  word the glossary gives to the whole flow, fetch included.

The reads of a workspace's plan (`planOf`, `countWorkspacesOnFree`) sat in the same
repository the handler wrote through.

What others do. In kgrzybek's modular monolith the `Subscription` aggregate has a method
per transition (`Renew`, `Expire`) and the payment one `MarkAsPaid`, each raising a domain
event: there the domain itself decides the subscription's term. Where a provider decides,
the local record is synced from it as a whole: Pay's `Subscription.sync(id)`, dj-stripe's
`sync_from_stripe_data`, Payum's `Sync` command ("ask the provider for the current state"),
kept apart from `Notify`, which only handles the provider's message.

## Decision

- **Stripe decides a subscription's transitions; our `Subscription` is a copy of it.** It has
  no method per transition and no guard on them: Stripe moves a subscription from any status
  to any other (`past_due → active`, `incomplete → incomplete_expired`, `active → canceled`),
  and a guard that refused one would make the copy differ from Stripe.
- **`Payer` is the aggregate root of a workspace's billing, and holds its subscriptions.** A
  subscription changes only through it. `Payer::syncSubscription($stripeSubscriptionId,
  $plan, $status, $at)` updates the payer's subscription with that id, or adds it, and
  refuses any new subscription while it holds one that is not `canceled`, whatever the new
  one's status, with `SecondSubscriptionException`, as #139 did.
  The rule of one is the payer's invariant over the set of its subscriptions; the partial
  unique index of 0037 stays as the database's guard. `Subscription::sync($plan, $status,
  $at)` is called only by the payer: plan and status are all that Stripe can change of what
  we keep.
- **`subscriptions.workspace_id` gets a foreign key to `payers.workspace_id`.** Both tables
  are Billing's, so [0018](0018-modules-meet-through-contracts.md) allows it, and a
  subscription means nothing without its payer.
- **The use case is a command: `Billing\Command\SyncSubscription`, handled by
  `SyncSubscriptionHandler`.** The handler fetches the subscription from Stripe outside the
  transaction, finds the payer by the subscription's customer
  (`PayerRepository::findOfCustomer()`), and calls `syncSubscription()`. An unknown customer
  and `SecondSubscriptionException` are logged as errors to `billing` and not retried, as
  0037 says.
- **A sync that fails after its retries is left to Messenger.** Its own
  `SendFailedMessageForRetryListener` already logs a `critical` with the message's class and
  the exception, and the message stays in `failed`, where `messenger:failed:show` shows the
  subscription's id. 0037's listener of `WorkerMessageFailedEvent`, which heard every failed
  message of the worker to log one class again in `billing`, goes away.
- **The plan is read apart from the aggregate.** `Billing\Query\WorkspacePlans` answers
  `planOf()` and `countOnFree()` with a query on `subscriptions`, without loading a payer.
  `SubscriptionRepository` goes away: a subscription is loaded through its payer. Reads stay
  direct calls; there is no query bus.
- **"Sync" is the word for the flow**, in the glossary, the command, the handler and the
  methods, in place of "refresh", which reads as renewing the subscription.
- **Billing announces no fact about a subscription yet** (0023). Nothing would listen.

## Alternatives considered

- **A method per transition on `Subscription`** (`activate()`, `enterGrace()`, `suspend()`,
  `cancel()`, `changePlan()`), each with a domain event, as `Task` does
  ([0015](0015-task-lifecycle-as-explicit-transitions.md)). It gives a place to react to one
  transition, for a table in the handler from Stripe's status to a method, and guards with
  nothing to refuse. Signal: the first reaction to one transition, such as the emails of
  failed payments.
- **The rule of one in the handler**, as #139 built it. No new mapping, but the handler
  decides a domain rule and grows a branch with each new one.
- **A domain service that checks the rule before a subscription is created.** It leaves the
  handler, but the rule still sits outside the aggregate it protects.
- **Reads left in `SubscriptionRepository`.** Nothing to move, but it would be the repository
  of an entity that is no longer a root, used by everything that reads a plan.
- **A query bus.** Reads in the project are direct calls; with one caller per read a bus adds
  a layer and nothing else.
- **A fact such as `SubscriptionChanged` in `Billing\Contract` now.** A class and a publish
  with no listener. Signal: the first listener — an email, the `grace` banner, Meet caching
  limits.
- **Keeping the failure listener, renamed `SyncFailureListener`.** The subscription's id
  in the log line and the `billing` channel, for a listener of every failed message that
  repeats Messenger's own `critical`. Signal: someone has to find a failed sync by the
  subscription's id in the logs, without access to the console.
- **Keeping "refresh".** It is the glossary's word since 0037, but read in the code it says
  "renew" and was misread so.

## Consequences

- Syncing loads the payer with all its subscriptions; cancelled ones stay, a handful per
  workspace.
- The rule of one is tested as a unit test of `Payer`; the handler's tests keep their cases
  and expectations.
- A migration adds the foreign key; every `subscriptions` row was written for a payer that
  exists, so no row blocks it.
- A reaction to a single transition, when it comes, starts by comparing the subscription's
  status before and after `sync`, or by moving to methods per transition; this record is then
  revisited.
- A failed sync is found by Messenger's `critical` in the main log, not in `billing`, and
  its subscription's id by `messenger:failed:show`.
- 0037's endpoint, one consumer, retries and what the copy grants are unchanged; the
  endpoint dispatches `SyncSubscription` in place of `RefreshSubscription`.
