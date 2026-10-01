# 0011. Answer someone else's task exactly like a missing one: `404`

- Status: accepted
- Date: 2026-09-30
- Implemented in: #58

## Context

Until #58 a `TaskVoter` behind `#[IsGranted]` answered `403` for a task that belongs to
another user, while a missing task answered `404`. The difference told a caller that a
task with that id exists and belongs to someone else.

## Decision

- A task is loaded only among the current user's own. `OwnedTaskValueResolver` queries by
  id and owner together and throws `NotFoundHttpException` when nothing is found, so a
  foreign task and a missing one give the same `404` with the same body.
- The resolver runs while the controller arguments are resolved, before
  `#[MapRequestPayload]` validates the body: a `PUT` with an invalid body to someone
  else's task answers `404`, not a `422` that would reveal the task.
- An id that is not a UUID is a `404` as well. Without an authenticated user the resolver
  throws `AccessDeniedException`, which the firewall answers with `401`.

RFC 9110 §15.5.4 allows a `404` to hide the existence of a forbidden resource; GitHub's
REST API answers private repositories the same way.

## Alternatives considered

- **Keep `403` from the voter.** Honest about the reason, but confirms that the id exists.
- **`#[IsGranted(..., statusCode: 404)]`.** It throws a plain `HttpException(404)`, which
  is logged at `error`: every request for someone else's id would dump the production log
  buffer, which [0009](0009-production-logging.md) had just stopped. Mapping
  `HttpException` to `info` instead would hide 5xx `HttpException`s too.
- **Check the owner in the controller body.** The body is validated before the controller
  runs, so an invalid `PUT` answers `422` and gives the task away.
- **`#[MapEntity(expr: ...)]`.** The expression has no access to the current user.

## Consequences

- No route answers `403` now. `TaskVoter` is removed, and `403` is gone from the OpenAPI
  description of the task endpoints. `ApiAccessDeniedHandler` stays for the permissions
  inside a shared project (roadmap, Phase 2), where a member may see a resource and still
  not be allowed to change it.
- The `404` is a `NotFoundHttpException`, already logged at `info`
  ([0009](0009-production-logging.md)).
- A client with a wrong or stale id cannot tell "deleted" from "not yours". That is the
  point of the decision, and it makes support questions harder to answer from the
  response alone.
- Ownership is checked by the query itself, so a new task endpoint is protected as soon
  as it takes its `Task` argument through the resolver — and unprotected if it loads the
  task any other way.
