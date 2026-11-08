---
name: implement
description: Build a change in symfony-todo-app that involves decisions - a new endpoint, a rule, a migration, a fix across modules - in agreed phases - questions, approaches, plan, tests first, code, verify, review, report. Use when Nikita asks to build or change something. A typo, a rename or a comment is made directly, without it.
argument-hint: "[what to build, or the path to its design doc or ADR]"
---

# Implement a change

What to build: $ARGUMENTS

The phases follow Anthropic's `feature-dev` workflow, cut to this project: its ADRs, its
checks, tests first. Each phase that says "wait" ends there; Nikita decides what comes next.

## 1. Understand

- Read what the change rests on: the design doc, the ADR, the issue, the discussion.
- Read the ADRs of the rules it touches, and a neighbouring feature that works the same
  way. For a change across several modules, a subagent may explore the code and report the
  files and flows that matter; read those files yourself before going on.
- Say in a few lines what you understood the change to be.

## 2. Questions (wait)

List what the design leaves open: behaviour at the edges, status codes, names in the API,
the data model, which module owns what, what happens to existing data. Ask them all at
once, each with your suggested answer. Wait for the answers. Skip this phase only when
nothing is open, and say so.

## 3. Approaches (wait)

When there is more than one reasonable way to build it, give two or three, each with what
it costs and what it makes harder later, and recommend one. Wait for Nikita's choice. If a
choice is a rule worth keeping, it becomes an ADR in this change.

## 4. Plan (wait)

- **Cases**, each with its answer: the main path, and those that break such code: someone
  else's resource (`404`), a viewer (`403`), an empty or invalid parameter (`422`), a
  finished task (`409`), two requests at once.
- **Files by module**, and any contract, migration or index.
- **Docs**: ADR, the README rule line, `docs/openapi.json`.
- **Not in this change.**

Wait for the go-ahead. The plan is the scope: a file outside it needs a reason in the
report, and another module's contract needs asking first.

## 5. Tests first

- A branch from a fresh `origin/main`: `feat/…`, `fix/…`, `refactor/…`, `chore/…`.
- For every case in the plan, a test first: an application test through HTTP, and a unit
  test for a domain rule, shaped like the neighbouring tests. Run them and show the output:
  each fails for the right reason, not on a typo. No code for a case before its test fails.
- A migration, service wiring, a rename, a refactor under existing tests and docs need no
  new test first; `make verify` covers them.

## 6. Code

- The smallest change that makes the tests pass, in the shape of the neighbouring feature:
  controller, DTO, a use case that owns one `wrapInTransaction()`, events, resource.
- The rules in `CLAUDE.md` hold. A failing check is fixed in the code, never loosened.
- `make verify-fast` as you go. After three failed attempts at the same problem, stop and
  report what was tried.

## 7. Finish

- `make verify` green.
- The docs from the plan: ADR, README, `make generate-openapi`, the roadmap if it moved.
- Run the `project-review` skill. Fix what it must; answer each "should fix" with a fix or
  a reason; bring its questions to Nikita.

## 8. Report (wait)

- What changed, as behaviour.
- The commands run and their result: the end of `make verify`, not "tests pass".
- How often `verify-fast` failed on the way; what the review found and what became of it.
- Open questions and what is not done.

Commit, push and open the pull request only when Nikita says so. The pull request follows
the earlier ones: what changes, tests, not here, docs; no Claude attribution, and Nikita is
neither assignee nor reviewer.
