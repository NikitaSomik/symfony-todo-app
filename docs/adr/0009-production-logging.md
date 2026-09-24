# 0009. Log in production per request: buffer quietly, write on failure, tag every record

- Status: accepted
- Date: 2026-09-23
- Implemented in: #39, #46

## Context

Logging every record of every request at `debug` produces noise and cost; logging only
errors loses the context that explains them. And a record found in the logs is only
useful if it can be tied to the request — and the user — it came from.

## Decision

- **Buffer per request.** In `prod`, Monolog's `fingers_crossed` handler keeps up to 50
  records of a request and writes them all to `stderr`, as JSON, once a record reaches
  `error`. `404` and `405` do not trigger the dump. How loud an expected domain exception
  is gets declared per exception class ([0007](0007-exception-mapping-and-error-normalizer.md)).
- **Warnings pass through** (`passthru_level: warning`, #39). A warning is written
  immediately even when the request ends without an error; `debug` and `info` stay in the
  buffer as context.
- **Every record carries `request_id`**, and a record of an authenticated request carries
  `user_id` — never the email (#46). The id is returned in the `X-Request-Id` header on
  every response, error responses included, and CORS exposes it to browser code.
- **The id is generated here** — a UUIDv7 — unless the request comes from a trusted proxy
  and carries an `X-Request-Id` made only of letters, digits, `.`, `_` and `-`, at most
  128 characters. Then it is reused, so the proxy's logs and ours share one id.

## Alternatives considered

- **No `passthru_level`.** Measured in #39: a request that logs a warning and then
  finishes normally writes nothing at all, so a warning never shows up in `prod`.
- **Lower `action_level` to `notice`.** Warnings become visible too, but each one dumps
  the whole 50-record buffer.

## Consequences

- A failure arrives with the records that led to it; a warning arrives alone, without
  that context.
- A client cannot choose what our logs say: an `X-Request-Id` from anyone but a trusted
  proxy is ignored. This relies on `TRUSTED_PROXIES` being right.
- A test proves that a plain password never reaches a log record, for successful and
  failed registration and login, stack traces included — the buffer dumps everything it
  holds once it opens.
