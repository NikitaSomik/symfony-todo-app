# 0032. A workspace buys a plan through Stripe Checkout, as its own Stripe customer, and Stripe is asked before a page opens

- Status: accepted
- Date: 2026-11-09
- Implemented in: #114

## Context

[0031](0031-billing-on-stripe-by-plan-tiers.md) settled that a workspace pays on Stripe,
buys on Stripe Checkout and manages its plan in the Customer Portal. Building the first
purchase raised questions it had not answered, and changed three of its points:

- who the customer in Stripe is, a person or the workspace, and what of it we keep;
- how to stop a workspace from paying twice when our copy of the plan follows a webhook
  that has not been built yet, and arrives seconds after the payment once it is;
- whether a personal workspace, which 0031 kept on Free, may be bought;
- how a price in the code survives the sandbox and the live account having different ids;
- what the customer sees when Stripe is slow or down, given that PHP-FPM, nginx and
  Stripe's client each have a timeout of their own.

The customer waits for the page's address, so the request has to call Stripe itself.

## Decision

- **A workspace pays as one Stripe customer, its `Payer`, and only the customer's id is
  kept.** The customer is created on the first purchase, with the email of the owner who
  buys and the workspace id in its metadata. The `payers` table holds the workspace id and
  the customer id; the invoice email, address and VAT number live on the customer in
  Stripe and are edited in the Customer Portal, so invoices may go to an address that
  belongs to no member. Two first purchases at once keep the customer saved first, in one
  upsert; the other customer stays in Stripe, empty.
- **A workspace with a subscription does not open a page.** Our copy of the plan is checked
  first; then Stripe is asked for the customer's subscriptions, and any that is not
  `canceled` or `incomplete_expired` (paid, being paid or owing) answers `409` with the
  code `already_subscribed`. Asking Stripe covers the time before the webhook writes our
  copy. A customer created by the same request has no subscription, so it is not asked
  about. Stripe's Checkout setting "Limit customers to one subscription" is switched on in
  the Dashboard of each account, sandbox and live, as a second guard: it sends a customer
  who already has a subscription to the Customer Portal instead of a second payment.
- **A page expires in an hour**, not Stripe's default day, so a forgotten page does not
  stay payable. Two pages opened before either is paid can still both be paid; the
  webhook step cancels and refunds the subscription that comes second for the same
  customer.
- **A personal workspace is bought like any other.** A user who wants more people where
  they already work moves that workspace to a paid plan instead of starting again in a new
  one. The personal mark only keeps it out of the count of team workspaces on Free.
- **A price is found by its lookup key**, `team` or `business`, the plan's own name, and
  its id is cached for an hour per plan. The code holds no price id, so nothing differs
  between the sandbox and the live account, and a price changed in the Dashboard reaches
  new pages within the hour.
- **The workspace id travels in the metadata of the Checkout session and of the
  subscription**, and as `client_reference_id`, so a webhook that arrives before
  `checkout.session.completed` still finds its workspace. The return address carries
  `{CHECKOUT_SESSION_ID}`. Opening a page changes no plan; only the payment does.
- **Opening a page calls Stripe in the request, outside any transaction, not through the
  outbox** 0031 plans for changes that touch both sides. The customer waits for the address,
  nothing of ours is written but a new customer's id, and a failure is an error the
  customer can retry.
- **When Stripe does not answer, the client is told to try again.** A connection failure,
  the rate limit or a 5xx from Stripe is `503` with the code
  `payment_provider_unavailable` and `Retry-After: 30`, both declared on the exception with
  Symfony's `#[WithHttpStatus]`. A request Stripe refuses otherwise, invalid or with a bad
  key, is our bug and stays a `500`. Each failed call is logged on the `billing` channel
  with Stripe's request id, HTTP status and code: as a warning when Stripe is unavailable,
  as an error when it refuses us.
- **Timeouts are ours, not the library's.** Stripe's client gives up connecting after
  3 seconds and waits `STRIPE_TIMEOUT_SECONDS` (30) for an answer, with one retry, instead
  of 30 and 80 seconds by default. nginx waits 75 seconds for PHP, longer than two attempts
  of one call, so a single call that fails ends in our `503`, not nginx's `504`.

## Alternatives considered

- **A Stripe customer per user**, reused across the workspaces they pay for. One customer
  for one person matches how Stripe is often used for consumers. But a user who owns two
  workspaces would carry two subscriptions on one customer, "one subscription per customer"
  could no longer mean "one per workspace", and when the paying owner leaves, the
  workspace's billing would leave with them.
- **Relying only on Stripe's "one subscription per customer" setting.** No call to Stripe
  before the page, one round trip less. The setting is a switch in each account's
  Dashboard that nothing in the code checks, and it acts on Stripe's page, after the
  customer has been sent there; our own `409` tells the client before.
- **A session with `customer_email` and no Customer.** Stripe creates a customer at
  payment, and there is nothing to keep beforehand. Every purchase would then create a new
  customer, so neither our check nor Stripe's setting would see a workspace's earlier
  subscription, and the Customer Portal would need that customer found again afterwards.
- **The `503` in `exceptions.yaml` and `Retry-After` from a listener of our own**, as
  [0007](0007-exception-mapping-and-error-normalizer.md) keeps every status in
  configuration. Built first, in this pull request: a marker interface in `Shared`, a
  listener placed between the exception map and the error page, and its tests, to set one
  header that Symfony's attribute already sets. The cost of the attribute is one
  exception whose status is read on its class, not in the configuration.
- **Price ids in environment variables.** Explicit and with no call to Stripe. Each account
  has its own ids, so every environment carries a copy that must match the Dashboard, and a
  new price is a deployment.

## Consequences

- One more call to Stripe before each page for a workspace that already has a customer.
- The "our `503`, not nginx's `504`" guarantee holds per call to Stripe, not per request. A
  request makes up to four calls (create the customer, ask for subscriptions, find the
  price when the cache is cold, open the page), each up to two attempts of 33 seconds, so a
  Stripe that is slow but answers can still end in nginx's `504`, and the page it opened
  goes unused and expires within the hour. Accepted: a Stripe that slow is rare, and the
  customer can try again.
- A customer created by a purchase that lost the race stays in Stripe, empty.
- "Limit customers to one subscription" lives in the Dashboard: a new Stripe account has to
  switch it on, and nothing in the code would notice if it were off.
- The webhook step owns the refund of a second subscription; until it lands, two pages paid
  within the same hour mean two subscriptions.
