# 0007. Declare how each exception is answered in configuration, and render errors with a normalizer

- Status: accepted — supersedes [0006](0006-errors-rendered-by-exception-subscriber.md)
- Date: 2026-09-21
- Implemented in: #38, #42

## Context

[0006](0006-errors-rendered-by-exception-subscriber.md) mixed three questions in one class
and answered them in code: which HTTP status an exception gets, whether its message may
reach the client, and whether it is an incident worth a log entry. It also took over
error handling from the framework instead of plugging into it.

## Decision

Each of the three questions is declared once, in its own place.

- **Status:** `framework.exceptions` in `config/packages/exceptions.yaml` maps a domain
  exception class to a status. Modules throw domain exceptions and know nothing about HTTP.
- **Visibility:** an exception whose message is written for API clients implements the
  `Shared\Http\ClientFacingException` marker. Any other exception is answered with the
  neutral status text, and one without a mapping with a plain `500 Server Error.`, so
  internal details never leak.
- **Log level:** `log_level` next to the status. An expected `409` is logged at `info`.

Rendering goes through `Shared\Http\JsonApiErrorNormalizer`, a serializer normalizer for
`FlattenException`, which Symfony's `SerializerErrorRenderer` calls. The Symfony
documentation names a normalizer as the tool for changing the contents of non-HTML error
output. `ApiRequestFormatListener` sets the request format to JSON under `/api/`, so a
client that sends no usable `Accept` header still gets JSON rather than an HTML page.

## Alternatives considered

- **Keep the subscriber** ([0006](0006-errors-rendered-by-exception-subscriber.md)). The
  documentation reserves `kernel.exception` for taking full control, which this project
  did not need.
- **A custom error controller.** The documentation's tool for changing how error pages are
  generated. The generation was fine; only the contents had to change.
- **Keep the expected `409` out of the logs with `excluded_http_codes`.** Tried in #38 and
  replaced before merging: it silences a status code for every exception that maps to it,
  while `log_level` states the intent for one exception class.

## Consequences

- `Shared` no longer imports anything from a module. A new domain exception needs one
  config entry and, if its message is meant for clients, the marker.
- Symfony's error pipeline runs unchanged: logging, the `FlattenException`, the renderer.
  A mistake in the normalizer shows up as a wrong response body — the first test run
  catches it — instead of as a missing log record.
- The normalizer must outrank Symfony's `ProblemNormalizer` (priority `-890`). If it did
  not, errors would silently switch to Problem Details.
- The first `framework.exceptions` entry whose class matches by `instanceof` wins, so the
  order of the entries matters.
