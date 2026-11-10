# 0033. A workspace has one open Checkout page: a new one closes the others, and a paid one answers 409

- Status: accepted
- Date: YYYY-MM-DD
- Implemented in: #PR

## Context

[0032](0032-a-workspace-buys-a-plan-through-stripe-checkout.md) refuses a page to a
workspace with a subscription, but it asks Stripe only when a page is opened. Two pages
opened before either is paid, in two tabs or by two owners, can both be paid, and 0032
settled that case by cancelling and refunding the subscription that comes second.

That charges a customer for something we already know we will take back. A refund costs a
fee, shows on the customer's statement and needs the webhook step to work before it can
happen.

Stripe's Checkout setting "Limit customers to one subscription" does not close the gap
either: its documentation does not say when it checks, at session creation, page load or
payment, and says nothing about two open pages.

Stripe can expire a Checkout session only while it is `open`; for one that is complete it
answers with an error.

## Decision

- **Before it opens a page, Checkout closes every open page of the workspace's customer.**
  It lists the customer's sessions with `status=open`, every page of the list, and expires
  each one. A customer created in the same request is skipped: it has no pages yet. The
  port names the step `PaymentProvider::closeOpenCheckoutPages()`.
- **The pages are closed first, the subscriptions checked after.** Once the pages are
  closed none of them can be paid, so the check of the customer's subscriptions that
  follows sees every payment made before; one paid meanwhile answers `409`
  `already_subscribed` through that check, like any subscription found in Stripe.
- **A page Stripe refuses to expire is read again.** If it is `complete` or `expired`, it
  was paid or ran out meanwhile, and Checkout goes on; if it is still `open`, the refusal is
  ours, logged as an `error` and answered `500`. Stripe's error message is not parsed.
- **Nothing is stored for it.** Stripe holds the open sessions; we keep no session id and
  no plan of an open page.
- **Two requests at the same moment are not serialized.** A double click opens two pages,
  but the browser follows only one address and the other expires within the hour. Paying
  twice takes two pages that were both shown and both paid, which the step above prevents
  for every page opened after the first.
- **What still slips through is caught, not refunded.** A second subscription for the same
  customer that reaches the webhook anyway is written to the `billing` log as an `error`
  with both ids, and its refund is made by hand in the Dashboard. The webhook step builds
  that check.

## Alternatives considered

- **Refund the second subscription from the webhook** (0032). Correct in the end, but the
  customer is charged first and the refund carries a fee; we would build an automatic
  money movement for a case we can prevent.
- **Keep the open session's id and plan on the payer**, give back the same page for the
  same plan and expire it for another. One call to Stripe less when the plan repeats, for
  two columns, a migration and a branch the list call does not need.
- **A lock per workspace around opening a page**, so that two simultaneous requests cannot
  both open one. It closes the double click, which costs nothing, for a new package and a
  lock held while Stripe is called; the webhook step defers the lock and names its signal.
- **Rely on Stripe's "one subscription per customer" setting.** Its timing is not
  documented and nothing in the code checks that it is on.

## Consequences

- More calls to Stripe before each page for a workspace that already has a customer: one
  listing of the open sessions, one expiry per open page, and one read per expiry Stripe
  refuses.
- A page an owner left open in another tab stops working when a new one is opened. That is
  the point, and the owner can open it again.
- Until the automatic refund exists, a second subscription is a manual task found in the
  log. The signal to automate it: it happens once in live mode.
