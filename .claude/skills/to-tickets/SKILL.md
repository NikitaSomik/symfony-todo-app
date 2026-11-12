---
name: to-tickets
description: Cut a settled design in symfony-todo-app into tickets - one per future pull request, each a complete slice with its cases, seams and blockers - for implement to build. Use when Nikita types /to-tickets after grill-with-docs or to-spec.
argument-hint: "[the spec's path, an ADR, a ticket to split, or nothing for the design just settled]"
disable-model-invocation: true
---

# Cut the tickets

What to cut: $ARGUMENTS, or the design settled in this conversation.

A ticket to split keeps its number for its first slice; the others are numbered after the
highest file, and a ticket that was blocked by it names the slice it needs.

A ticket is one future pull request: the plan `implement` builds from, starting at the
tests. This skill writes no code.

## 1. Read what it rests on

The spec when there is one, otherwise the conversation or the ticket to split; the ADRs it
names; [GLOSSARY.md](../../../GLOSSARY.md); the code of the modules involved, to see what
each slice touches. When the work shows a sign in [to-spec](../to-spec/SKILL.md) and no
spec holds its design, stop and propose `/to-spec` first.

## 2. Draft the slices

- A slice cuts a narrow but complete path: migration, entity, use case, endpoint, its
  tests and docs, and it fits one session with a fresh context. A slice per layer (the
  migration, then the endpoint) is not one: it cannot be tested on its own.
- A change that is easier after a refactor gets the refactor as its own ticket, first.
- A wide refactor goes expand, migrate, contract: the new form beside the old, the callers
  moved in batches, each blocked by the expand, and the old form removed in a ticket blocked
  by every batch.
- `Blocked by` names the tickets that must be merged first. None is the best case: tickets
  without blockers can be built at once.

## 3. Agree the cut

Show the slices as a numbered list, blockers first: the title, `Blocked by`, what it
delivers. Say where you would merge or split and which blocker you doubt, and why, so that
"yes" accepts it; Nikita confirms or corrects the size and the blockers. Show it again until
he approves it.

## 4. Write them

- The folder of the spec, or `/mnt/project-files/tickets/` when it exists (a cloud
  thread), otherwise `.claude/tickets/`, which git ignores. A file `NNN-what-it-builds.md`,
  numbered after the highest file there in the order of the approved list, so that among
  the new tickets a blocker has the lower number, from [ticket-template.md](ticket-template.md), with
  `status: draft`.
- Each ticket names its ADRs and its spec; the behaviour as cases, each with its answer,
  the allowed one and the refused one (`404`, `403`, `422`, `409`, two requests at once);
  the seam of each case, from the spec when there is one; the modules, with any contract,
  migration or index; the docs; what is not in it; the questions left open. Its size
  follows the change: a field with its validation takes five lines.
- In the domain's words, with modules and contracts by name; no file paths and no code,
  which go stale: `implement` finds the files.
- A question left open on purpose is a ticket with `status: waiting` and the `signal:` that
  would make it needed; `implement` does not take it until Nikita opens it.
- The Docs of the last ticket that implements an ADR say that it turns the ADR `accepted`.
- Show the tickets. A ticket Nikita approves turns `open`: his approval of its plan, which
  `implement` builds from. The next step is `implement` with a ticket whose blockers are
  done.
