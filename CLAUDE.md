# CLAUDE.md

A Symfony 8 task manager API: workspaces, tasks, audit log, notifications and billing on
Stripe. Modules live in `src/<Module>/` (Auth, Workspace, Task, AuditLog, Notification,
Billing, Shared), each with its own `di.php` and `routing.php`; deptrac guards which module
may use which, and another module is reached only through its `Contract/`.

## Running things

PHP runs only in Docker. The host PHP is too old for the project.

- Any `php`, `composer`, `bin/console` or `vendor/bin/*` command: `docker compose exec app …`
  (or the `make` target that wraps it, `make help` lists them).
- The checks: `make check-code` (php-cs-fixer, PHPStan, deptrac, PHPUnit). CI also lints the
  container and validates the schema (`.github/workflows/`).
  `make fix-cs` fixes style.
- `docs/openapi.json` is generated: `make generate-openapi`, never edited by hand.
- Never run `docker compose down -v`: it deletes the database volume.
- Secrets go to `.env.local`, never to a tracked file. Stripe keys are test-mode (`sk_test_`).

## Code

- English only in the repository: names, comments, test data, commits. Non-ASCII test data
  uses Latin with diacritics (`MÜLLER@example.com`).
- Prefer the framework's standard tool (`#[MapRequestPayload]`, `#[MapQueryString]`,
  validator constraints, `#[AsDecorator]`) to custom listeners, resolvers or parsers. Read the
  framework's source to the end before deciding it cannot do something.
- Meaning on the surface: a condition that decides behaviour is a plain `if`, not `?->` with
  `??`; `?? throw` only for "cannot happen"; no bare `[]` or `null` arguments that carry a
  meaning; named arguments where neighbouring parameters share a type; no leading backslash
  on native functions.
- A comment only for a reason the code cannot show. None that restate a name or a body, and
  none in Docker or compose files.
- Names: a listener is named after what it does (`UnassignTasksOfMember`), no `Listener`
  suffix; an interface after its role; `find…` may return null, `get…` throws. When a name is
  in doubt, look up how others name it before choosing.
- API: responses are JSON:API 1.1; request bodies are plain JSON with
  `#[MapRequestPayload(acceptFormat: 'json')]` written out each time; sorting is `sort` +
  `direction`.

## Tests

- Every behaviour has an allowed case and a refused one. Before calling a change ready,
  list its behaviours as allowed / refused and compare with the tests; 100% line coverage
  does not mean the cases are covered.
- When the question is what the code does, measure it with a temporary test, then delete it.

## Docs

- A significant decision is an ADR in `docs/adr/` (template there), updated in the same PR as
  the code, with `Implemented in: #PR`. "Alternatives considered" lists only options that
  were really weighed. A later change to an old ADR's fact gets a short dated note.
- Untracked files in `docs/`, `templates/` and `install.md` are local notes: never commit them.

## Git

- Branches: `feature|fix|refactor|chore/<module>-<what>`.
- Commits: one line, `type(module): lowercase imperative summary`, no period, no body, no
  trailers. PR descriptions carry no generated-by lines.
- Commit, push and open a PR only after the diff is shown and approved. The owner merges.

## Working together

- Answer the question asked; do not jump to plans, PRs or side topics.

### Dead end

Signals, observable rather than a mood: the same symptom is reported a second time after
its fix; the same place is edited a third time, or an own change is reverted; the replies
have grown shorter and sharper. Sharpness or swearing means "the approach is wrong and
information is being lost", not a reason to apologise. "Stop, meta-position" from the person
is the same signal.

Then stop editing and step into the meta-position (systems thinking, Weinberg). The object is
the process of the search, not the person.

1. Write down what is verified by a fact, what is refuted, and what is only assumed.
2. Draw the dead end as a diagram of cause and effect, three to six nodes, and name the
   reinforcing loop: failed attempts anchoring the context; shorter messages → fewer facts →
   more guessing; a patch on the symptom instead of a diagnosis.
3. Find the assumption every failed attempt shares, and test it first, by the cheapest
   measurement.
4. Tell the two cases apart: knowledge not engaged — restate the task from scratch; a fact
   missing — observe it (a log, a test, a query, the source, the documentation). Neither is
   cured by another guess.
5. If the context is poisoned with attempts, hand the task to a fresh agent, or propose a new
   session, with the facts from step 1 only — no history of attempts, no own hypotheses.
6. Show the person, in three lines: the loop, the shared assumption, the next step.
