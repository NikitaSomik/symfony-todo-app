---
kind: change            # change | epic
status: open            # open | waiting | in-progress | done
signal:                 # for waiting: what would make it needed
epic:                   # the epic's file, if this ticket is part of one
why:                    # for an epic: the signs that made it one
branch:                 # set by implement when it creates the branch
pr:                     # set when the pull request opens
---

# NNN. What it builds, as a sentence

ADRs: `docs/adr/NNNN-....md`

## Behaviour

The cases, each with its answer: the main path and the refused ones that apply (someone
else's resource `404`, a viewer `403`, an empty or invalid parameter `422`, a finished task
`409`, two requests at once).

- ...

## Files

By module, with any contract, migration or index.

## Docs

ADR status, the README rule line, `docs/openapi.json`, the roadmap.

## Not in this ticket

## Open questions

Each with the signal that would settle it.

---

An epic (`kind: epic`) has, instead of the sections above:

## Goal

## Tickets, in order

## The flow across them
