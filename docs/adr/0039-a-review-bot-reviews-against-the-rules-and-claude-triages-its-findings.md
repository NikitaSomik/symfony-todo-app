# 0039. A review bot reviews against the project's rules, without the session, and Claude triages its findings

- Status: accepted
- Date: YYYY-MM-DD — when the decision reached `main`, taken from git history
- Implemented in: #PR

## Context

CodeRabbit reviews every pull request. Claude Code writes the code under `CLAUDE.md`, the
ADRs and the skills, and reviews it first with `project-review`, which checks the change
against its plan. Two agents now speak about the same diff, and they can contradict each
other: the bot can ask for what an ADR refused, and Claude can either break the ADR or
argue in every pull request.

Its first full review (py-transcript-service #1) ran on CodeRabbit's defaults: 27 findings
in three rounds. 23 were fixed, among them real bugs (an audit that passed when its export
failed, a released migration that could be deleted or renamed, CI without `permissions`).
Two were withdrawn after a reply and two were left for Nikita. Each later round found
narrower cases in the fixes of the round before. The bot also wrote its own summary into
the pull request's description, which the `pr` skill writes, and offers an "Autofix" that
commits outside `make verify` and the hooks.

CodeRabbit reads `CLAUDE.md` as review guidelines by default, but not the ADRs or the
glossary.

## Decision

- The bot reviews against the project's rules: `CLAUDE.md`, the ADRs and `GLOSSARY.md`,
  set in `.coderabbit.yaml`. It does not see the plan, the ticket or the session that
  produced the code.
- The rules live in one place. `.coderabbit.yaml` holds only what concerns the bot: what
  it skips, which of its tools run, its tone. It does not repeat what CI checks.
- The bot advises; Claude writes the code. Claude answers each finding with the
  `answer-review` skill: fixed, declined with a reason, or left for Nikita when it touches a
  rail, an ADR or an agreed behaviour. The bot's autofix is not used, and it does not write
  the pull request's description.
- From the third round of a review on, new minor findings about the previous fixes go to
  Nikita instead of another round.

## Alternatives considered

- **The bot reviews blind, from the diff alone.** An outside view that could question the
  decisions themselves. It lost because the same findings against accepted ADRs would come
  back in every pull request (a `403` for a foreign task, a foreign key between modules),
  and each would need an answer. A wrong rule is better questioned once, as a question
  about its ADR.
- **The bot also sees the plan and the ticket.** It would understand the intent, and
  inherit the author's reasoning and its blind spots with it. `project-review` already
  checks the change against its plan; a second reviewer is worth more when it does not
  share the author's context. Its independence comes from another model and a fresh view
  of the diff, not from not knowing the rules.
- **The rules copied into `path_instructions`.** Two copies of a rule drift apart.
- **Claude applies every finding.** Fast, and it lets the bot change ADRs and rails, and
  turns rounds into a spiral of ever narrower fixes.

## Consequences

- When a rule is wrong, the bot will rarely say so by itself; it is asked to raise it once,
  as a question about the ADR.
- Each pull request costs a round of answers. A finding declined for the same reason a
  second time is a sign that a rule in `CLAUDE.md` or an ADR is unclear, for `/retro`.
- CodeRabbit reads at most 50 guideline files per review. With 38 ADRs, `CLAUDE.md` and
  the glossary there is room; past about 45 ADRs the patterns need narrowing.
- Revisit when the bot's findings are mostly declined over several pull requests (its
  profile or scope is wrong), or when its findings are mostly about the rules rather than
  the code.
