---
kind: spec
status: open            # open | done (when all its tickets are)
why:                    # the signs that made a spec needed
---

# NNN. What the work achieves, as a sentence

ADRs: `docs/adr/NNNN-....md`

## Problem

What is wrong or missing today, for whom.

## Decisions

What was decided, each with its ADR, and the decisions that got no ADR, each with its
reason in a line.

## The flow

The path through the modules, in the order it runs: the request, the transaction, the facts
announced, the listeners, the calls to Stripe, the retries.

## Behaviour

The cases across the flow, each with its answer: the main path and the refused ones (someone
else's resource `404`, a viewer `403`, an empty or invalid parameter `422`, a finished task
`409`, two requests at once, a webhook twice, late or out of order).

## Seams

Where each case is tested, as agreed with Nikita: each seam with what it catches and misses,
and the neighbouring tests it follows.

## Rollout

Existing data, a migration in steps, a flag. "None" when there is none.

## Not in this work

## Open questions

Each with the signal that would settle it.
