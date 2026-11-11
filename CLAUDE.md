# symfony-todo-app

A task API built as a modular Symfony monolith. The point of the project is the decisions:
each one is an ADR in `docs/adr/` (template and rules: `docs/adr/README.md`), and the README
links every rule to its ADR.

## How we work

- Nikita sets the direction. System design and architecture are decided together, in
  discussion, before code, with the `grill-with-docs` skill: questions in rounds, then the
  ADRs. Nikita runs `/to-spec` for work that shows one of its signs (more than one ticket,
  two modules, an asynchronous flow, a rollout in steps, three ADRs) and `/to-tickets` to cut
  the work into tickets. Claude writes the code.
- Answer the question asked; do not jump to plans, pull requests or side topics.
- Open questions are settled before coding, not guessed. Where there is a choice, Claude
  lays out the options with their cost and recommends one. "Not needed now" is a valid
  answer when it names the signal that would make it needed.
- A change that involves decisions is built with the `implement` skill: an approved ticket
  or one plan with its questions and approaches, tests first, code, verify, reviews,
  report. A typo, a rename or a comment is made directly.
- A decision worth keeping gets an ADR: Context / Decision / Alternatives considered /
  Consequences, updated in the same pull request as the code.
- Code, commits, ADRs and pull requests are in English.
- One task, one branch from `main`, named `feature|fix|refactor|chore/<module>-<what>`;
  never commit to `main`, never rewrite a pushed branch.
- Commit locally as the work goes; push or open a pull request only when Nikita says so;
  Nikita merges. A commit not yet pushed may be amended.
- Commits: one line, `type(module): lowercase imperative summary`, no period, no body, no
  trailers; author `Mykyta <nikitasomusev@gmail.com>` (Nikita), unsigned
  (`git -c commit.gpgsign=false commit`).
- Pull requests are described with the `pr` skill: no Claude attribution or "Generated
  with" line; do not assign Nikita or request his review.
- After a task that went harder than it should have, Nikita may run `/retro`: it proposes
  changes to the checks, skills and rules, and changes nothing by itself.
- Untracked files in `docs/`, `templates/` and `install.md` are Nikita's local notes: never
  commit them.

## Commands

PHP runs only in Docker; the host PHP is too old. Any `php`, `composer`, `bin/console` or
`vendor/bin/*` command goes through `docker compose exec app …` or the `make` target that
wraps it (`make help`). `make up` first.

- `make verify-fast`: what the branch changed: style, `lint:container`, PHPStan, Deptrac,
  unit tests and the changed tests, plus no new ignores and no edited migration from
  `main`. The Stop hook runs it.
- `make verify`: everything CI runs. Work is done when this is green.
- `make fix-cs`: fix the code style.
- One test: `docker compose exec -T app vendor/bin/phpunit tests/Application/Task/SomeTest.php`
- `docs/openapi.json` is generated with `make generate-openapi`, never edited by hand.
- Secrets go to `.env.local`, never to a tracked file. Stripe keys are test mode (`sk_test_`).

## Done means

- `make verify` is green, and the report shows the commands and their output, not
  "tests pass".
- Every behaviour has an allowed case and a refused one, each with its test; 100% line
  coverage does not mean the cases are covered.
- A failing check is fixed in the code. Checks are never loosened: no edits to their
  configs, no `@phpstan-ignore`, no skipped tests, no test expectation changed to fit
  the code unless the change of behaviour was agreed.
- Before a change is shown for a pull request, the `project-review` and `security-review`
  skills have gone through it, and each finding is fixed or answered.

## Rules the code does not show at a glance

- Modules: Auth, Workspace, Task, Billing, Notification, AuditLog, Shared. A module uses
  another only through its `Contract/` namespace, and refers to another module's records by
  id, without a foreign key ([0018](docs/adr/0018-modules-meet-through-contracts.md)).
  Deptrac enforces it.
- Facts are announced after the commit, through `EventPublisher` ([0023](docs/adr/0023-facts-announced-after-the-commit.md)).
- A resource outside the user's reach answers 404, not 403 ([0011](docs/adr/0011-foreign-task-answers-404.md));
  an empty query parameter is a 422 ([0029](docs/adr/0029-an-empty-query-parameter-is-refused.md)).
- Responses are JSON:API 1.1; request bodies are plain JSON, with
  `#[MapRequestPayload(acceptFormat: 'json')]` written out each time; sorting is `sort` +
  `direction` ([0008](docs/adr/0008-json-api-responses-plain-json-requests.md)).
- A task changes status only through its transitions in the entity ([0015](docs/adr/0015-task-lifecycle-as-explicit-transitions.md)).
- Code asks for a permission, never for a role ([0022](docs/adr/0022-code-asks-for-a-permission.md)).

## Code

- The framework's standard tool (`#[MapRequestPayload]`, `#[MapQueryString]`, validator
  constraints, `#[AsDecorator]`) before a custom listener, resolver or parser. Read the
  framework's source to the end before deciding it cannot do something.
- Meaning on the surface: a condition that decides behaviour is a plain `if`, not `?->` with
  `??`; `?? throw` only for "cannot happen"; no bare `[]` or `null` arguments that carry a
  meaning; named arguments where neighbouring parameters share a type; no leading backslash
  on native functions (`sprintf`, not `\sprintf`).
- A comment only for a reason the code cannot show; none that restate a name or a body, and
  none in Docker or compose files.
- A name reads at its call site without opening the body, in the domain's words
  ([GLOSSARY.md](GLOSSARY.md)). A method is named for all it does or returns: one that also
  logs, saves or decides says so, or is split; one that returns something says what. A word
  that fits any value (`data`, `info`) gives way to what the value is here, and so does a
  library's term when the domain has its own word. When no honest name fits, the design is
  wrong, not the name. Room for cases that do not exist yet is no reason for a vaguer name.
- Domain code carries no type suffix such as `Entity`, `Service`, `Manager` or `Interface`
  (a role noun such as `Generator` or `Formatter` is part of the name), except `Exception`:
  a port is named after its role (`PaymentProvider`, its adapter `StripePaymentProvider`); a
  fact for other modules is a past event (`TaskAssigned`), and two facts are two events, not
  one with a mode; a use case is a verb and an object with `handle()` (`CreateTask`); an
  exception is named after the refusal (`LastOwnerException`). A listener of a module's
  event does one thing and is named after it. It may listen to several events when the
  action is the same (`UnassignTasksOfMember`, for a member who left or lost
  `work_on_tasks`), but each fact written to the audit log has a listener of its own
  (`LogCreated`, `LogRenamed`), not one for a module's history. A class that
  plugs into the framework keeps the framework's suffix (`RequestIdProcessor`, `TaskVoter`,
  `WorkspaceContextValueResolver`); a listener of a framework or library event ends in
  `Listener` (`RequestIdListener`), not `Subscriber`. `find…` may return null, `get…` throws.
  A test is named for the behaviour it pins (`registerShouldStoreEmailLowercased`).
- When a name is in doubt, look up how others name it to choose between options, not to
  keep the current one.
- Non-ASCII test data is Latin with diacritics (`MÜLLER@example.com`).

## Dead end

A dead end is a fact, not a mood: the same symptom reported again after its fix, the same
place edited a third time or an own change reverted, three failed attempts at one problem,
replies growing shorter and sharper, or "stop, meta-position" from Nikita. Sharpness means
the approach is wrong and information is being lost, not a reason to apologise. Stop
editing and follow the `dead-end` skill.

## Rails

In `.claude/settings.json`:

- Deny rules keep the check configs, CI, the Makefile and `bin/verify-fast` unedited, and
  refuse commands that throw away work or data, such as `docker compose down -v`.
  `git push` and opening a pull request ask first.
- After each edit a hook runs php-cs-fixer in the app container.
- Before Claude finishes, a hook runs `bin/verify-fast` in the container; while it fails,
  Claude keeps working.

The rails are changed by Nikita.
