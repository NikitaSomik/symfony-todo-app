# 0008. Follow JSON:API for responses and query parameters, keep request bodies plain JSON

- Status: accepted
- Date: 2026-09-23
- Implemented in: #43, #44

## Context

Since #7 the API had answered with JSON:API-shaped documents built by a small hand-written
layer in `src/Shared/Api`, but full compliance with
[JSON:API 1.1](https://jsonapi.org/format/) was never a stated goal. Without a written
boundary every change reopened the question of how far to go.

To answer it with numbers, full compliance was built end to end on the branch
`feature/api-json-api-compliance`, kept for reference and never merged: request documents,
`PATCH` with partial attributes, `406`/`415` negotiation, `400` for unknown parameters.

## Decision

Follow the specification wherever Symfony's standard tools reach it, and stop where it
would take custom infrastructure around them.

**Followed:** the `application/vnd.api+json` media type and the `jsonapi` member on every
response; resource objects with `type`, string `id` and `attributes`; foreign keys as
`relationships`; `201` with a `Location` header for a created task; `filter[...]` and `page[...]` parameters
with `first`/`last`/`prev`/`next` links; error objects with `status`, `detail` and a
`source` — `pointer`, `parameter` or `header`.

**Deliberate deviations:**

| The specification asks for | What the API does | Why |
|---|---|---|
| Request bodies as resource documents | Plain JSON through `#[MapRequestPayload]` | Reading documents needs a custom body resolver |
| `PATCH` with partial updates | `PUT` with full replacement | Status changes are dedicated commands ([0015](0015-task-lifecycle-as-explicit-transitions.md)), so `PUT` replaces only plain fields |
| `400` for an unknown query parameter | Ignored by `#[MapQueryString]` | The framework default was preferred to custom checks |
| `sort=-created_at` syntax | `sort` and `direction`, `422` for an invalid value | See below |

The auth endpoints are RPC-style and stay outside the specification; their errors still
use the `errors` envelope.

## Alternatives considered

- **Full compliance.** Measured on the experiment branch: about 450 lines of code in
  `src/Shared/Http` (733 with comments), +1024/−114 lines in `src` and `config`,
  +564/−95 in tests, and a body resolver tied to Symfony's listener priority `-10100`.
  The friction appeared only where JSON:API goes beyond HTTP — the request envelope — and
  JSON:API models CRUD over resources, while the project is moving towards commands.
- **API Platform.** Near-complete JSON:API almost for free, but it owns the architecture:
  state providers and processors in place of the use-case services, specifications and
  explicit module boundaries this project is built around. Its JSON:API support is not a
  standalone library, so using it means adopting the whole framework.
- **Problem Details (RFC 9457) for errors.** Symfony's native error format. Lost because
  the success responses already follow JSON:API, and one envelope for both spares the
  client a second format to parse.
- **The JSON:API `sort` syntax.** Adopted in #44 and reverted within it: it needed a parser
  and a `-` in the default value, for sorting by several fields that nothing needs.

## Consequences

- A generic JSON:API client such as Ember Data will not work without an adapter;
  hand-written clients lose nothing.
- A typo in a query parameter — `filter[stauts]` — silently returns an unfiltered list.
  `QueryParameterTest::unknownParameterShouldBeIgnored` pins this down as intended.
- A query DTO used with `#[MapQueryString]` must implement `Shared\Http\QueryPayload`, or
  its validation errors are reported with `source.pointer` instead of `source.parameter`.
- If strict compliance ever becomes a goal, the experiment branch is the starting point,
  and the choice is again between extending `src/Shared/Http` and moving to API Platform.
