# 0036. Meet asks the monolith, on an internal endpoint, what a user may do and what the plan allows

- Status: proposed
- Date: YYYY-MM-DD — when the decision reached `main`, taken from git history
- Implemented in: #PR

## Context

Who may start or join a meeting depends on facts the monolith owns: a user's permissions
in a workspace ([0022](0022-code-asks-for-a-permission.md)) and the workspace's plan
([0031](0031-billing-on-stripe-by-plan-tiers.md)). Inside the monolith a module asks
`WorkspaceAccess` and `PlanLimits`; Meet runs in another process
([0034](0034-meetings-run-in-a-separate-go-service.md)). A meeting is also the only feature
with a cost per minute — bandwidth — so a plan has to bound it.

## Decision

- **Meet asks the monolith at two moments: when a meeting starts and when someone joins.**
  The answer gives the user's permissions in the workspace and the plan's limits for
  meetings. An empty list of permissions means the user is not a member, and Meet answers
  `404`, as for a meeting that does not exist.
- **The endpoint is internal**: under `/internal`, reachable only on the private network
  between the containers; nginx does not route it from outside.
- **Meet proves itself with a service token** in a header, a secret held in both services'
  environments. A call without it, or with a wrong one, is refused.
- **Starting a meeting takes a new permission, `host_meetings`.** Owners and members hold
  it, viewers do not; anyone who can `view` may join. When it is implemented, the table
  of [0022](0022-code-asks-for-a-permission.md) gets a dated note.
- **A meeting holds as many participants as the plan holds members:** 3 on Free, 10 on
  Team, 50 on Business, the same `memberLimit()`. A participant is a user, by id: joining
  from a second device replaces the first, so one person cannot take two places.
- **A meeting lasts at most 40 minutes on Free, 120 on Team, 240 on Business.** The limit
  lives in `Plan`, next to the member limit; Meet keeps no table of plans. When it is
  implemented, [0031](0031-billing-on-stripe-by-plan-tiers.md) gets a dated note.
- **Limits are fixed when a meeting starts.** Meet sets the room's maximum of participants
  in LiveKit and closes the room when its time is up; a change of plan applies from the
  next meeting.
- **The limits follow `BILLING_LIMITS`** like the others: with it `off`, a meeting is not
  bounded by the plan.
- **When the monolith does not answer, Meet refuses** to start or join, with `503`; it
  never guesses a permission.

## Alternatives considered

- **Meet keeps a copy of members and plans, fed by events over RabbitMQ.** Meet would work
  without the monolith, but the events it needs do not exist yet, the messages are
  PHP-serialized, and an event that cannot be sent is only logged
  ([0023](0023-facts-announced-after-the-commit.md); the outbox is deferred), so the copy
  would drift without anyone noticing.
- **Mutual TLS between the services.** Stronger than a shared token, but it needs a
  certificate authority of our own and rotation, for two services on one private network.
- **A table of plans and their meeting limits in Meet.** Meet would not depend on the
  monolith for limits, but a plan would be described in two places that can disagree.
- **Starting a meeting with `view`.** No new permission, but a viewer would spend the
  plan's minutes. **With `work_on_tasks`.** No new permission either, but a permission
  about tasks would start deciding about calls.
- **Limits checked again during a meeting.** A downgrade would take effect at once, at the
  price of polling the monolith or a channel of events.
- **A separate number of meeting participants per plan.** Only members join, so a limit
  above the member limit could never be reached, and one below it would only surprise.

## Consequences

- An internal API in the monolith: one more contract to version, tested from both sides.
- Every start and every join waits on one call to the monolith, so Meet sets a short
  timeout on it.
- The service token is a secret to rotate in two environments at once.
- A workspace that downgrades keeps its running meetings on the old limits until they end.
- Revisit when Meet must work while the monolith is down, when more services call the
  internal API (then mutual TLS), or when the bandwidth bill calls for a limit on meetings
  at once.
