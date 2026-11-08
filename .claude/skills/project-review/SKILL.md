---
name: project-review
description: Review this branch's changes on two axes - against the approved plan, and against the project's own rules (module contracts, transactions and facts, permissions and 404s, JSON:API errors, migrations, tests, ADRs). Use after implementing a change and before a pull request, or when asked to review a diff.
argument-hint: "[the approved plan, pasted] [base: branch, default origin/main]"
context: fork
background: false
allowed-tools: Read, Grep, Glob, Bash(git diff:*), Bash(git log:*), Bash(git show:*), Bash(git status:*), Bash(git ls-files:*)
disallowed-tools: Edit, MultiEdit, Write, NotebookEdit
---

# Review against the project's rules

You review a change in symfony-todo-app with fresh eyes. You read the code, not the author's
summary of it, and you do not edit anything.

## What to review

Arguments: `$ARGUMENTS`. They carry the approved plan of the change, and may name a base
as `base: <branch>`; the base is `origin/main` otherwise.

1. `git diff <base>...HEAD` for the committed changes, `git diff HEAD` and
   `git ls-files --others --exclude-standard` for the rest.
2. Read each changed file in full where the diff alone hides the context, and the code it
   calls. Read the ADRs the change touches, in `docs/adr/`.

`make verify` already checks style, PHPStan level 10, Deptrac imports, the schema against
the mapping and the tests. Do not report what it would catch; look for what it cannot.

## Against the plan

When the arguments carry a plan, check the change against it before the rules:

- Every case in the plan has its behaviour in the code and a test that would fail without
  it: the main path, `404`, `403`, `422`, `409`, the race.
- Nothing beyond the plan: a file, an endpoint or a behaviour the plan does not name is a
  finding, unless the report gives its reason.
- What the plan put under "not in this change" is not in it.

Without a plan, say so in the report and review against the rules only.

## Against the rules

Go through every item that applies to the change. An item that does not apply is skipped
silently.

**Module boundaries** ([0018](../../../docs/adr/0018-modules-meet-through-contracts.md))
- Services, events and responses carry another module's ids, never its entities.
- No foreign key across modules, in migrations or in the mapping: all were withdrawn in #82.
- No SQL join across modules, unless in a query class named for it and recorded as coupling.
- Another module's data for a list is fetched through its contract for the whole page at
  once, not per row.

**Transactions and facts** ([0012](../../../docs/adr/0012-synchronous-audit-log-one-transaction.md), [0023](../../../docs/adr/0023-facts-announced-after-the-commit.md))
- A complete use case owns one `wrapInTransaction()` and does not call `flush()` itself; a
  building block used inside another operation never flushes.
- What must hold when the request ends (audit entries, taking tasks off someone) goes
  through the event dispatcher inside the transaction.
- A fact for other modules is a class in `Contract/`, published through `EventPublisher`
  after `wrapInTransaction()` returns, and not for a change that was refused or changed
  nothing. A fact that cannot be sent is logged; the request still succeeds.

**Access** ([0011](../../../docs/adr/0011-foreign-task-answers-404.md), [0020](../../../docs/adr/0020-a-task-belongs-to-a-workspace.md), [0022](../../../docs/adr/0022-code-asks-for-a-permission.md))
- Code asks for a `WorkspacePermission` (through a voter or `WorkspaceAccess`), never
  compares roles.
- A resource outside the user's reach answers `404` with the same body as a missing one.
  It is loaded in a value resolver, before the payload is validated, so an invalid body to
  someone else's resource is a `404`, not a `422`. An id that is not a UUID is a `404`.
- No answer reveals which ids exist ([0028](../../../docs/adr/0028-tasks-filtered-by-assignee-an-id-me-or-none.md)).

**API** ([0007](../../../docs/adr/0007-exception-mapping-and-error-normalizer.md), [0008](../../../docs/adr/0008-json-api-responses-plain-json-requests.md), [0029](../../../docs/adr/0029-an-empty-query-parameter-is-refused.md))
- Modules throw domain exceptions and know nothing about HTTP; the status is mapped in
  `config/packages/exceptions.yaml`. Only a message written for API clients implements
  `ClientFacingException`.
- Responses are JSON:API: `201` with `Location` for a created resource, errors with
  `status`, `detail` and a `source`.
- A query parameter: empty is a `422` (`#[Assert\NotBlank(allowNull: true)]`), invalid is a
  `422` with `source.parameter`.
- A changed endpoint has `docs/openapi.json` regenerated (`make generate-openapi`).

**Domain** ([0015](../../../docs/adr/0015-task-lifecycle-as-explicit-transitions.md), [0021](../../../docs/adr/0021-a-task-has-an-assignee-who-can-work.md))
- A task changes status only through its transitions in the entity; finished work stays
  finished.
- A rule lives in the entity or the use case, not in the controller.

**Database**
- An index on a table that can be large is built `CONCURRENTLY`, in a migration with
  `isTransactional(): false`
  ([0028](../../../docs/adr/0028-tasks-filtered-by-assignee-an-id-me-or-none.md)).
- A new filter or sort has an index that serves it.
- `down()` undoes `up()`.
- Two requests racing for the last place (a seat, a limit) are serialised by a lock, as the
  member limit is ([0031](../../../docs/adr/0031-billing-on-stripe-by-plan-tiers.md)).

**Tests**
- New behaviour has an application test through HTTP, and the domain rule a unit test.
- The cases that break such code are covered when they apply: someone else's resource
  (`404`), a viewer (`403`), an empty or invalid parameter (`422`), a finished task, a race.
- Every changed or deleted assertion in an existing test is listed under questions: it is
  fine only if the change of behaviour was agreed.

**Decisions and reuse**
- A change that adds or alters a rule needs an ADR (`docs/adr/template.md`) and its line in
  the README's rule list. Say which rule, and whether an existing ADR should be amended.
- Before accepting new code, search for what already does it: a specification, a value
  object, a helper, a factory. Name the existing one.

**Code**
- The rules under "Code" in `CLAUDE.md`. Names in the domain's words, as the ADRs use them.

## Report

Three groups, most serious first. Leave a group out when it is empty.

- **Must fix**: a bug, a broken rule from the checklist, a case of the plan missing, new
  behaviour without a test.
- **Should fix**: a missing index, duplication, an unclear name, a missing ADR line,
  something beyond the plan.
- **Questions for Nikita**: design choices and changed test expectations. Do not decide:
  give the options and what each costs.

Each finding: `path:line`, what is wrong, why (the ADR or rule), and the fix. No praise and
no summary of the diff. When nothing is found, say so and list the checklist sections you
went through.

End with one line: this review covers the project's rules; `/code-review` hunts generic
bugs and `/security-review` security issues.
