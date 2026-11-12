---
name: implement
description: Build a change in symfony-todo-app that involves decisions - a new endpoint, a rule, a migration, a fix across modules - in agreed phases - an approved ticket or one plan for Nikita to approve, then tests first, code, verify, reviews and a report. Use when Nikita asks to build or change something. A typo, a rename or a comment is made directly, without it.
argument-hint: "[the ticket to build, or what to build and its ADR]"
---

# Implement a change

What to build: $ARGUMENTS (a ticket, or what to build with its ADR; a spec is built
through its tickets)

The phases follow Anthropic's `feature-dev` workflow, cut to this project: its ADRs, its
checks, tests first. Nikita is asked at the plan and at the report.

## 1. Understand

- First bring the tickets' folder up to date: a ticket whose `pr:` is on `origin/main`
  (`git log origin/main --grep "(#N)"`) turns `done`, and a spec (`kind: spec`) whose
  tickets are all `done` turns `done` too.
- Read what the change rests on: the ticket and its spec, the ADR, the discussion.
- A ticket is the approved plan. Take it only while it says `status: open` (never `draft`,
  `waiting` or a spec) and every ticket in its `blocked-by` is `done`: set
  `status: in-progress` first and read the file again, so that no other session takes it.
  A ticket `in-progress` is taken only when its `branch:` is the branch being continued.
- A ticket that needs a second pull request or a contract it does not name is not taken:
  propose the split, which `/to-tickets` cuts from the ticket.
- Continuing a branch: its plan in `.claude/plans/`, its pull request with the review
  comments and CI, and `git log origin/main..`. Merge a fresh `origin/main` into it; a
  pushed branch is never rebased. The plan then covers only what is left.
- Read the ADRs of the rules it touches, and a neighbouring feature that works the same
  way. For a change across several modules, a subagent may explore the code and report the
  files and flows that matter; read those files yourself before going on.
- Say in a few lines what you understood the change to be.

## 2. Plan (wait)

Skipped when a ticket is the plan: go to phase 3 once phase 1 is through. Otherwise one
message, in this order:

- **Questions** the design leaves open (behaviour at the edges, status codes, names in the
  API, the data model, which module owns what, existing data), each with the suggested
  answer.
- **Approaches**, when there is more than one reasonable way: two or three, each with what
  it costs and what it makes harder later, one recommended. A choice that is a rule worth
  keeping becomes an ADR in this change.
- The plan, built on the suggested answers and the recommended approach:
  - **Cases**, each with its answer: the main path, and those that break such code: someone
    else's resource (`404`), a viewer (`403`), an empty or invalid parameter (`422`), a
    finished task (`409`), two requests at once.
  - **Seams**: where each case is tested.
  - **Files by module**, and any contract, migration or index.
  - **Docs**: ADR, the README rule line, `docs/openapi.json`.
  - **Not in this change.**

Wait. "Ok" accepts the suggested answers and the recommended approach; Nikita's corrections
change the plan, which is shown again only if they change its cases. The approved plan is
the scope: a file outside it needs a reason in the report, another module's contract needs
asking first, and a change of behaviour beyond the plan goes back to Nikita.

## 3. Tests first

- A branch from a fresh `origin/main`, named as `CLAUDE.md` says, unless continuing one.
- The approved plan saved to `.claude/plans/<branch>.md`, which git ignores, so that a new
  session continues from it and the branch, without this one's history. For a ticket the
  file holds only the ticket's path, and the ticket gets `branch:`: a correction goes into
  the ticket, the one copy.
- The scope holds in this phase and the next: when a ticket turns out to need a second pull
  request or a contract it does not name, stop, propose the split for `/to-tickets`, and go
  back to Nikita instead of widening it.
- For every case in the plan, a test first, at its seam, shaped like the neighbouring tests.
  The expected value comes from the ticket or the approved plan, the ADR or the provider's
  documentation, never from running the code under test. Run them and show the output: each fails for the right
  reason, not on a typo. No code for a case before its test fails.
- A migration, service wiring, a rename, a refactor under existing tests and docs need no
  new test first; `make verify` covers them.

## 4. Code

- The smallest change that makes the tests pass, in the shape of the neighbouring feature:
  controller, DTO, a use case that owns one `wrapInTransaction()`, events, resource.
- The rules in `CLAUDE.md` hold. A failing check is fixed in the code, never loosened.
- `make verify-fast` as you go. A dead end, as `CLAUDE.md` defines it, goes to the
  `dead-end` skill.

## 5. Finish

- `make verify` green.
- The docs from the plan: ADR, README, `make generate-openapi`, the roadmap if it moved.
  The ADR this change implements turns `accepted`, in the record and in the table, with the
  last ticket that implements it, as the tickets' Docs say; a fact of an older ADR that this
  change makes untrue gets a dated note there. `Date` and `Implemented in` are filled once
  the pull request has its number.
- Commit the work locally, not pushed: `security-review` reads the commits against
  `origin/HEAD`, which a fresh clone may lack (`git remote set-head origin main`).
- Run the `project-review` skill, which reads the plan of the branch, and the
  `security-review` skill. Fix what they must; answer each "should fix" with a fix or a
  reason; bring their questions to Nikita.

## 6. Report (wait)

The report, which becomes the pull request description:

- What changed, as behaviour.
- The commands run and their result: the end of `make verify`, not "tests pass".
- How the work went: how often `verify-fast` failed, what each review found and what
  became of it, and the session's tokens, summed from the `usage` of its messages in its
  log in `~/.claude/projects/`.
- Open questions and what is not done. A question that outlives the change becomes a ticket
  of its own, written as `to-tickets` writes one, with `status: waiting` and its `signal:`,
  and the report names it.

Push and open the pull request when Nikita says so (`CLAUDE.md`), described with
the `pr` skill. A red check on it is fixed on the branch. The ticket gets `pr:` when the pull
request opens; phase 1 of a later session marks it `done` once it is merged.
