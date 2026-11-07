# symfony-todo-app

A task API built as a modular Symfony monolith. The point of the project is the decisions:
each one is an ADR in `docs/adr/` (template: `docs/adr/template.md`), and the README links
every rule to its ADR.

## How we work

- Nikita sets the direction. System design and architecture are decided together, in
  discussion, before code. Claude writes the code.
- Open questions are settled before coding, not guessed. A decision worth keeping gets an
  ADR: Context / Decision / Alternatives considered / Consequences.
- Show the change first. Commit, push or open a pull request only when Nikita says so;
  Nikita merges.
- Commits: `type(scope): what changed`, author `Mykyta <nikitasomusev@gmail.com>`,
  unsigned (`git -c commit.gpgsign=false commit`), no trailers.

## Commands

Everything runs in Docker: `make up` first.

- `make verify-fast`: the changed PHP files: style, `lint:container`, PHPStan, Deptrac,
  unit tests and the changed tests. The Stop hook runs it.
- `make verify`: everything CI runs. Work is done when this is green.
- `make fix-cs`: fix the code style.
- One test: `docker compose exec -T app vendor/bin/phpunit tests/Application/Task/SomeTest.php`

## Done means

- `make verify` is green, and the report shows the commands and their output, not
  "tests pass".
- A failing check is fixed in the code. Checks are never loosened: no edits to their
  configs, no `@phpstan-ignore`, no skipped tests, no test expectation changed to fit
  the code unless the change of behaviour was agreed.
- After three failed attempts at the same problem, stop and report what was tried.

## Rules the code does not show at a glance

- Modules: Auth, Workspace, Task, Billing, Notification, AuditLog, Shared. A module uses
  another only through its `Contract/` namespace, and refers to another module's records by
  id, without a foreign key ([0018](docs/adr/0018-modules-meet-through-contracts.md)).
  Deptrac enforces it.
- Facts are announced after the commit, through `EventPublisher` ([0023](docs/adr/0023-facts-announced-after-the-commit.md)).
- A resource outside the user's reach answers 404, not 403 ([0011](docs/adr/0011-foreign-task-answers-404.md));
  an empty query parameter is a 422 ([0029](docs/adr/0029-an-empty-query-parameter-is-refused.md)).
  Responses are JSON:API ([0008](docs/adr/0008-json-api-responses-plain-json-requests.md)).
- A task changes status only through its transitions in the entity ([0015](docs/adr/0015-task-lifecycle-as-explicit-transitions.md)).
- Code asks for a permission, never for a role ([0022](docs/adr/0022-code-asks-for-a-permission.md)).
- Comments only where the code is not obvious. `sprintf`, not `\sprintf`.

## Rails

Hooks in `.claude/settings.json`, scripts in `.claude/hooks/`:

- `guard.py` refuses edits to the checks and to these rails, edits to migrations already on
  `main`, new `@phpstan-ignore` or skipped tests, and edits outside `.claude/scope.txt`
  when that file exists (one glob per line; ask before widening it).
- `format_php.py` runs php-cs-fixer and PHPStan on each edited PHP file.
- `stop_verify.py` runs `bin/verify-fast` before Claude finishes, up to three times.

The rails are changed by Nikita, or in a session started with `CLAUDE_GUARD_UNLOCK=1`.
