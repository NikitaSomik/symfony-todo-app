---
status: draft           # draft | open (approved) | waiting | in-progress | done
signal:                 # for waiting: what would make it needed
spec:                   # the spec's file, if there is one
blocked-by:             # the tickets' files that must be merged first
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

## Seams

Where each case is tested: an application test through HTTP, a use case, a domain rule, an
adapter against its fake.

## Modules

What changes in each module, with any contract, migration or index.

## Docs

ADR status (`accepted` only with the last ticket that implements it), the README rule line,
`docs/openapi.json`, the roadmap.

## Not in this change

## Open questions

Each with the signal that would settle it.
