# 0031. A workspace pays on Stripe for a plan sized by its members; Stripe decides the status, the application derives access

- Status: proposed
- Date: 2026-10-29
- Implemented in: to come, step by step (see Consequences)

## Context

A workspace is a team ([0019](0019-workspaces-members-and-roles.md)), and nothing limits
its size or charges for it. A paid plan is next: a small team works for free, a larger one
pays. Payments go through Stripe — Billing for subscriptions, hosted Checkout to buy,
the Customer Portal to manage, Tax for the EU — with cards and SEPA, in the EU first.

Billing differs from everything built so far in two ways. Money is involved, so a lost or
repeated action costs someone: a charge without access, access without a charge. And the
truth lives in another system that talks back late, twice and out of order, through
webhooks. Most of the decisions below answer one of the two.

There is no frontend yet; one comes later. The API is its only client until then.

## Decision

### What is sold

- **A workspace subscribes, not a person.** One subscription per workspace; the people who
  join are its members, and none of them pays for themselves.
- **Plans are tiers sized by the number of members:** Free up to 3, Team up to 10,
  Business up to 50. Every member counts — owner, member and viewer alike. A tier has one
  price for the workspace, monthly or yearly; prices live in Stripe and are a product
  decision, not code.
- **A team that outgrows its tier moves to the next one.** No extra charge per person.
- **On Free an owner has one team workspace.** The personal workspace created at
  registration does not count toward it.
- **The limit is checked where a member is added and where a workspace is created**,
  under a lock on the workspace, so two invitations racing for the last place cannot both
  pass. Over the limit the answer is `403` with the code `plan_limit_exceeded`.

### Who pays

- **Paying is a permission, `manage_billing`, held by owners**
  ([0022](0022-code-asks-for-a-permission.md)). A workspace always keeps an owner
  ([0019](0019-workspaces-members-and-roles.md)), so it always has someone who can pay.
- **The payer's details are their own record, `Payer`:** the invoice email, address and
  VAT number. Invoices may go to an address that belongs to no member.

### Where the truth is

- **Stripe decides the subscription status; we keep a copy.** On every webhook the
  subscription is fetched again from Stripe and its status written down: `active` →
  active, `past_due` → grace, `unpaid` or `paused` → suspended, `canceled` or
  `incomplete_expired` → canceled, `incomplete` → pending. The body of the webhook is a
  signal to fetch, never the state itself, so a late event cannot roll the copy back.
- **No timer of ours changes the status.** Grace is the window in which Stripe retries a
  failed charge: two weeks for a card, after which Stripe marks the subscription unpaid.
  Both settings live in the Stripe Dashboard and are recorded here. Our scheduler sends
  reminders and reconciles; it never moves a subscription.
- **Access is derived, never stored.** One function of the plan and the status:
  active and grace get the plan's limits, grace with a banner; suspended makes the
  workspace read-only until the debt is paid; canceled falls back to Free, read-only while
  it has more members than Free allows. Signing in, the profile and the billing endpoints
  answer in every status, so someone who owes can always pay.
- **Not paying never touches roles.** Taking `work_on_tasks` away would unassign every open
  task ([0021](0021-a-task-has-an-assignee-who-can-work.md)) and lose the assignments for
  good; suspension is a check on top of permissions instead.

### How it moves

- **Buying is Stripe Checkout; managing is the Customer Portal.** Card forms, 3-D Secure,
  retries and receipts stay on Stripe's pages. The workspace id travels in the metadata of
  the subscription and the customer, so a webhook that arrives before
  `checkout.session.completed` still finds its workspace. One open Checkout per workspace.
- **A plan change has its own endpoint whose answer says what comes next.**
  `POST /api/v1/workspaces/{id}/billing/plan-change` with `{"plan": "business"}` answers
  `{"next": "redirect", "url": …}`: a Portal session opened on the confirmation of that
  very change. A frontend that confirms payment itself can later be answered
  `confirm_payment` with a client secret, or `done`, without breaking a client that knows
  only `redirect`.
- **An upgrade applies at once and is invoiced at once** (proration invoiced immediately).
  **A downgrade applies at the end of the paid period**, and only if the team already fits
  the lower tier.
- **Limits follow the webhook, not the click.** Until Stripe reports the change as paid,
  the workspace keeps its old plan.

### How it stays reliable

- **Webhooks land in an inbox.** The endpoint checks the signature, stores the event once
  by its id and answers; a worker processes it, locking the workspace's subscription
  before fetching from Stripe, so two events for one subscription cannot overwrite each
  other with older data.
- **No call to Stripe inside a database transaction.** What must reach Stripe is written
  as a message in the same transaction as the change — an outbox on Messenger's Doctrine
  transport, or the outbox Symfony 8.2 brings if it is adopted by then
  ([0023](0023-facts-announced-after-the-commit.md) named billing as the reason) — and a
  worker sends it with the message id as the idempotency key.
- **Every night the subscriptions are reconciled with Stripe**, from Symfony Scheduler, to
  catch a webhook that never arrived.
- **Payments and payers outlive the workspace.** No cascade from the workspace: invoices
  are kept for as long as the law asks.

### How it is released

- **Shipping and switching on are separate.** The code lands in the steps below, while
  limits are governed by one setting, `BILLING_LIMITS`, for every workspace at once:
  `off` checks nothing; `shadow` checks and logs who would be refused, and refuses nobody;
  `on` refuses.
- **Limits go `on` last.** A limit nobody can buy their way out of would only cut teams
  off, so the order is: `off` with the code in place, `shadow` to learn how many teams are
  affected, Checkout and webhooks, an email to the owners concerned, and `on` thirty days
  later. Going back is changing the setting, without a deployment. The setting is removed
  once `on` has held.
- **What already exists keeps working.** A workspace over its limit keeps its members and
  an owner keeps the workspaces they have; only adding beyond the limit is refused, until
  the team buys a plan. Owners of such workspaces are told by email thirty days ahead.
- **A personal workspace is marked, not guessed.** `personal` is a new column, filled for
  the earliest workspace each user created, because a personal workspace is created at
  registration. Being its owner is not enough, since an owner may be promoted in a team
  older than their account, and its name proves nothing: it can be renamed, and a team may
  call itself Personal. A report of what would be marked comes before the update. A personal workspace
  stays on Free, up to three members, and cannot move to a paid plan.
- **Migrations run before the code that needs them**, and filling a column is a step of
  its own, so old and new code can run side by side.
- **A plan lives in Billing's own table.** A workspace without a row there is on Free,
  so no existing row is touched, and Workspace stays unaware of billing. Billing implements
  a contract declared by Workspace, so the only dependency is Billing → Workspace.
- **Support changes a plan with a console command**, which leaves an audit entry saying who
  changed what.
- **Each switch is rehearsed.** There is no deployment yet, so the migrations and the moves
  from `off` to `shadow` to `on` are played on a local copy seeded with workspaces over
  their limits, following a written release plan and runbook.

## Alternatives considered

- **No setting: limits on from the first step.** One path fewer in the code and in the
  tests, but limits would arrive before anyone could pay, and the only way back would be
  a revert.
- **A share of workspaces at a time, or a flag service such as Unleash.** A real gradual
  rollout, for more code or one more service to run. Worth it once one setting for everyone
  proves too coarse.
- **Keeping teams over the limit on their old terms for good, or making them read-only
  after some months.** The first gives up what they would pay; the second breaks work that
  was allowed yesterday.
- **A price per member.** Revenue grows with the team and there is no jump between tiers.
  It brings back everything tiers avoid: a seat count kept in sync with Stripe on every
  change of members, a charge on every addition, a race for the last paid seat, rules for
  removing seats without gaming the next invoice. Worth it once teams stall at a tier
  boundary or ask to pay per person.
- **Charging only for those who work, viewers free** — counting members with
  `work_on_tasks`, as Figma counts editors. Fairer to teams with many observers, but it
  ties billing to permissions and needs a cap on free viewers. It belongs with per-member
  pricing.
- **Our own plan change on our own pages**, with Stripe Elements, a preview of the
  proration and 3-D Secure in the browser. The best experience, and there is no frontend
  to host it. The `next` field leaves room for it.
- **The Customer Portal alone, without our endpoint.** Almost no code, but the plan is then
  chosen on Stripe's page, which cannot show our limits, and an answer like
  `plan_limit_exceeded` has nothing to point to.
- **Access stored as a flag, or grace counted by our own timer.** Two sources of truth: on
  day 14 the timer suspends while Stripe, still retrying, reports `past_due`, and the next
  webhook brings the workspace back. Access flickers.
- **The change applied at once and invoiced with the next bill** (Stripe's default). A
  team could upgrade, add forty people and cancel before paying the difference.
- **A command bus for the calls to Stripe.** Its pilot (#90) was closed; services stay
  services, and the outbox gives the delivery guarantee a bus would not.
- **A free trial of Team.** Free already lets a small team start; a trial is a parameter of
  Checkout to add when there is a reason to.

## Consequences

- Until `on`, the limits are checked and not enforced, and `BILLING_LIMITS` is one more
  thing to test: every check has three modes until the setting goes.
- The eleventh member moves a team from Team to Business. That is the price of having no
  seats; a tier between the two is the answer if teams stop there.
- A team larger than fifty cannot buy on its own.
- How long grace lasts and what happens after the last retry are settings in the Stripe
  Dashboard, one for the whole account: a change there changes behaviour without a commit.
  A test on a Stripe test clock follows a refused card to suspension, and this record
  names the settings.
- Access changes seconds after payment, when the webhook arrives. The page a customer
  returns to fetches the subscription itself so they do not wait.
- Upgrades are separate small charges, each with a fee and possibly 3-D Secure; Stripe's
  pages handle the confirmation.
- Selling inside a future iOS app would fall under Apple's in-app purchase rules; billing
  stays on the web.
- Built in steps, each with its own pull request: plans and limits without Stripe, off;
  shadow mode; the outbox and Checkout; webhooks; plan changes; the email to owners over
  their limits; limits on; failed payments, cancelling and SEPA; reconciliation and alerts;
  load. The status becomes `accepted` when the first step
  reaches `main`, and this record links each step as it lands.
