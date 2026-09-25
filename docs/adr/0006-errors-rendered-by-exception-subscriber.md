# 0006. Render API errors in a `kernel.exception` subscriber

- Status: superseded by [0007](0007-exception-mapping-and-error-normalizer.md)
- Date: 2026-03-26
- Implemented in: #7

## Context

Every API failure — validation, a domain conflict, a missing resource, an unexpected
exception — had to reach the client in one JSON envelope instead of Symfony's default
error page.

## Decision

`Shared\Http\ApiExceptionSubscriber` listened to `kernel.exception`, recognised the
exception and called `setResponse()` with a JSON error document. The subscriber itself
decided each status: `EmailAlreadyTakenException` became `409`, a validation failure
`422`, an `HttpExceptionInterface` kept its own status, anything else became `500`.

## Why it was replaced

- **`Shared` depended on a module.** To answer `409`, the subscriber imported
  `Auth\Exception\EmailAlreadyTakenException`. Every new domain exception would have added
  another import and another branch to shared code.
- **A 500 was answered but not logged.** `setResponse()` stops the event's propagation.
  Registered at priority `0`, the same as `ErrorListener::logKernelException()`, the
  subscriber ran first, so an unexpected exception produced a response and no log record.
  #29 moved the subscriber below `0`, which made the correctness of logging depend on a
  priority number.
- **The framework's own rendering was switched off.** Setting the response also cancelled
  `ErrorListener::onKernelException()`, so everything Symfony does when it renders an
  error had to be reimplemented by hand.
