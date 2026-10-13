# 0029. An empty query parameter is refused, not read as absent

- Status: accepted
- Date: 2026-10-13
- Implemented in: #99

## Context

A parameter of the task list sent with an empty value — `filter[status]=` — was answered
in one of two ways, decided by the constraint on its field rather than by anyone:
`filter[status]=`, `sort=`, `direction=` and `page[...]=` with a `422`, while
`filter[due_from]=`, `filter[due_to]=`, `filter[search]=` and `filter[assignee]=` were
ignored, because `Date`, `Length` and `Regex` let an empty string through. The next
parameter would have behaved however its constraint happened to.

## Decision

- **An empty value is a `422` on that parameter**, for every parameter of the list. A
  client that has nothing to filter by leaves the parameter out.
- **Each field says so itself**: `#[Assert\NotBlank(allowNull: true)]` next to its other
  constraints. A parameter left out is still `null` and means no filter.
- **A search of only spaces is empty too** (`normalizer: 'trim'`).

## Alternatives considered

- **Read an empty value as absent, for every parameter at once** — a request listener that
  drops empty values before the query is mapped, as Laravel's `ConvertEmptyStringsToNull`
  does for every request. Friendlier to a client that sends whole forms, but it changes the
  request out of sight of the DTO, and a client's mistake passes silently.
- **Leave it as it was.** Nothing to build, and every new parameter decides by accident.

## Consequences

- The behaviour of a parameter can be read from its field.
- A client must leave out the parameters it has no value for; the list has no client of
  its own yet that would have to change.
- An unknown parameter is still ignored ([0008](0008-json-api-responses-plain-json-requests.md)):
  checking names would take custom infrastructure, checking values is what the DTO is for.
