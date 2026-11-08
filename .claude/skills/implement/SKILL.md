---
name: implement
description: Build a change in symfony-todo-app that involves decisions - a new endpoint, a rule, a migration, a fix across modules - in agreed phases - one plan for Nikita to approve, then tests first, code, verify, reviews and a report. Use when Nikita asks to build or change something. A typo, a rename or a comment is made directly, without it.
argument-hint: "[what to build, or the path to its design doc or ADR]"
---

# Implement a change

What to build: $ARGUMENTS

The phases follow Anthropic's `feature-dev` workflow, cut to this project: its ADRs, its
checks, tests first. Nikita is asked at the plan and at the report.

## 1. Understand

- Read what the change rests on: the design doc, the ADR, the issue, the discussion.
- Continuing a branch: its plan in `.claude/plans/`, its pull request with the review
  comments and CI, and `git log origin/main..`. Merge a fresh `origin/main` into it; a
  pushed branch is never rebased. The plan then covers only what is left.
- Read the ADRs of the rules it touches, and a neighbouring feature that works the same
  way. For a change across several modules, a subagent may explore the code and report the
  files and flows that matter; read those files yourself before going on.
- Say in a few lines what you understood the change to be.

## 2. Plan (wait)

One message, in this order:

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
  session continues from it and the branch, without this one's history.
- For every case in the plan, a test first: an application test through HTTP, and a unit
  test for a domain rule, shaped like the neighbouring tests. Run them and show the output:
  each fails for the right reason, not on a typo. No code for a case before its test fails.
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
- Open questions and what is not done.

Commit, push and open the pull request when Nikita says so (`CLAUDE.md`), described with
the `pr` skill. A red check on it is fixed on the branch.
