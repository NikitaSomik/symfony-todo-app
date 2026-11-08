---
name: retro
description: Look back at a finished task in symfony-todo-app and propose changes to the setup around the code - checks, hooks, skills, CLAUDE.md, ADR rules - most serious first. Run when Nikita asks for a retro.
argument-hint: "[the task, pull request or session to look at; default this session]"
disable-model-invocation: true
---

# Retro

Task: $ARGUMENTS, or this session when empty.

The retro changes the environment, not the code: what would have made this task go right
the first time. It proposes; nothing is changed until Nikita picks.

## 1. Collect the facts

- The session: this conversation, or the one named (session logs are in
  `~/.claude/projects/`).
- The pull request: its description, review comments, CI runs, and `git log` of its
  branch.
- The numbers from the report: how often `verify-fast` failed, what `project-review` and
  `security-review` found, how many times Nikita corrected the work, the session's tokens
  (the `usage` of its messages in the log).

## 2. Find where time or quality was lost

- Every correction from Nikita, and what he had to say twice.
- Every failed check and review finding, and whether a check could have caught it earlier.
- Every guess, and every question to Nikita that the repository could have answered.
- Searching for the same file or command more than once.
- A rule that was ignored, did nothing, or contradicts the code.

## 3. Pick the cheapest fix that lasts

For each loss, in this order of preference:

1. A deterministic check: a test, a PHPStan or Deptrac rule, a hook, a CI step.
2. An item in the `project-review` checklist, for a judgement call.
3. A step in a skill, for a procedure.
4. A line in `CLAUDE.md`, only for what no check can see and every task needs: each line
   is read on every request.
5. Nothing, when it was a one-off.

Removing a rule that did nothing is a fix too.

## 4. Propose

Most serious first. For each:

- **What happened**, with the evidence: a quote, `path:line`, a link.
- **The change**: the file and the exact text.
- **What it costs**: tokens on every request, CI time, maintenance.

Then the task's numbers in three lines. Wait for Nikita to pick. Picked changes go through
a branch and a pull request as `CLAUDE.md` says; a change to the rails
(`.claude/settings.json`, the Makefile, CI, a check's config) is prepared as a patch for
Nikita to apply.
