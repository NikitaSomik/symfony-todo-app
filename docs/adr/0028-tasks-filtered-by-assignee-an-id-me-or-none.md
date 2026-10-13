# 0028. Tasks are filtered by assignee with one parameter: a user id, `me` or `none`

- Status: accepted
- Date: 2026-10-13
- Implemented in: #98

## Context

A task has at most one assignee ([0021](0021-a-task-has-an-assignee-who-can-work.md)), but
the list of a workspace could not be narrowed by it. A member wants the tasks they hold,
a lead wants the ones nobody holds, and anyone may want those of a colleague.

## Decision

- **One parameter, `filter[assignee]`, takes a user id, `me` or `none`.** GitHub's issue
  list (`assignee=<login>|none`) and GitLab's (`assignee_id=<id>|None`) work the same way.
- **`me` is turned into the id of the user asking in the controller.** Past it, the filter
  is an `AssigneeFilter` — a user or nobody — and the query never learns who asked.
- **Anything else is a `422` on `filter[assignee]`.** An id has at most nine digits, so
  it always fits the 32-bit `assignee_id` column; a longer one would reach PostgreSQL and
  fail there with a `500`. An empty value means no filter, as for `due_from` and `search`.
- **An id is not checked against the users.** The id of someone outside the workspace
  returns an empty list, not an error: a `422` for an unknown id would let anyone signed
  in find out which ids are registered. A former member still holds the tasks they
  finished, and filtering by them shows those.
- **The tasks are indexed by `(workspace_id, assignee_id)`**, in place of the index by
  `workspace_id` alone, which it covers. On 300,000 tasks in 500 workspaces a filtered list
  and its count take 0.4 ms instead of 2.4 ms, and the unfinished tasks handed over by
  `reassign` are found as fast; an unfiltered list does not change. The index is built
  `CONCURRENTLY`, so tasks stay writable while it is.

## Alternatives considered

- **A parameter for the id and another for the cases without one**
  (`filter[assignee]=<id>`, `filter[assignment]=mine|unassigned`). Each stays one type, but
  the two can contradict each other, and every contradiction needs its own error.
- **No `me`: the client sends its own id.** It can, from `GET /api/v1/profile`, but a link
  to "my tasks" would then differ for every user, and the client would need the profile
  first.
- **`*` for tasks held by anyone, and several values at once** (`me,none`). No screen needs
  them yet; either can be added without breaking a client.

## Consequences

- The list answers "mine", "nobody's" and "someone's" with one request each.
- `me` and `none` cannot be user ids, which are numbers.
- An id of a billion or more is refused. Users would need a `BIGINT` key well before that,
  and the pattern would change with it.
