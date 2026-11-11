---
name: to-spec
description: Write the spec of a settled design in symfony-todo-app - the decisions, the flow across modules, the seams to test at - for work that shows one of its signs, so that it outlives the grill session. Use when Nikita types /to-spec after grill-with-docs.
argument-hint: "[what to spec, if not the design just settled]"
disable-model-invocation: true
---

# Write the spec

What to spec: $ARGUMENTS, or the design settled in this conversation.

A spec is the synthesis of a settled design, not a new interview: what was decided, the flow
across the tickets and where it is tested. ADRs keep the decisions worth keeping; the spec
keeps the rest of what the tickets need and this session would otherwise take with it.
`to-tickets` cuts the work from it.

## 1. Is a spec needed

Go through the signs:

1. more than one ticket, with an order between them;
2. two modules or more, or a change to a `Contract/` or an event;
3. an asynchronous flow or states: a webhook, a queue, retries, a state machine;
4. a rollout in steps: existing data migrated, a flag, a shadow mode;
5. three ADRs or more from this discussion.

One sign is enough. With none, say so in one line and hand over to `/to-tickets`: the
ticket carries the design of a small change by itself.

## 2. Read what it rests on

The conversation, the ADRs it wrote or touched, [GLOSSARY.md](../../../GLOSSARY.md), and
the code of the modules involved. A decision the design left open is not settled here: it
goes back to `grill-with-docs`, or into the spec as an open question with the signal that
would settle it.

## 3. Agree the seams

A seam is where a behaviour is tested: through HTTP in an application test, at a use case,
at a domain rule, at an adapter against its fake (`FakeStripeHttp`). The fewer seams, the
better; the existing ones first. Propose them, each case with its seam, and wait for
Nikita's answer before writing.

## 4. Write it

- The folder: `/mnt/project-files/tickets/` when it exists (a cloud thread), otherwise
  `.claude/tickets/`, which git ignores. A file `NNN-what-it-achieves.md`, numbered after the
  highest file there, from [spec-template.md](spec-template.md), `status: open`.
- In the domain's words. Modules, contracts, events and tables by name; no file paths and no
  code, which go stale before the last ticket is built. A snippet only when it states a
  decision more precisely than a sentence can, such as the shape of a payload.
- Show it. Nikita's approval of the spec is the approval of its design; the next step is
  `/to-tickets` with its path.
