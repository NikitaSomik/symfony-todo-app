# 0025. A use case that changes data is a command, handled on a bus that owns its transaction

- Status: proposed
- Date: 2026-10-12
- Implemented in: #90, as a pilot on `AssignTask` only

## Context

Every use case that changes data opens its own transaction with `wrapInTransaction`, and
since [0023](0023-facts-announced-after-the-commit.md) the ones that announce a fact must
also remember to publish it after that call returns, through `EventPublisher`. Both are a
discipline each service has to follow; nothing enforces them, and a service that publishes
inside its transaction would announce a change that may still be rolled back.

The modules are moving towards feature slices and CQRS. Symfony Messenger can carry the
write side: a command bus with the `doctrine_transaction` middleware runs a handler in a
transaction it opens, flushes and commits, and an event dispatched with
`DispatchAfterCurrentBusStamp` leaves only once that has succeeded.

The pilot moves one use case, assigning a task, and leaves `ReassignTasks` as it was, so the
two can be compared.

## Decision

- **A use case that changes data is a command and its handler**, in
  `Command/<UseCase>/` of its module: `Task\Command\AssignTask\AssignTask` carries ids, and
  `AssignTaskHandler` does the work.
- **The controller dispatches the command on `command.bus`** and renders the entity it had
  already resolved; the handler finds the same object in Doctrine's identity map, so the
  response shows the change without a second query.
- **The bus owns the transaction**: no `wrapInTransaction`, no `flush` in a handler.
- **A fact for other modules is dispatched on `event.bus` with
  `DispatchAfterCurrentBusStamp`**, from inside the handler. Messenger holds it until the
  command has been handled and committed, and drops it if anything failed.
- **Reads stay plain services and repositories.** A query bus would return results through
  Messenger for no gain.
- **Commands are handled synchronously.** Only events go to the queue.

## Alternatives considered

- **Keep the services.** No new concepts, and the code reads as plain method calls. The
  transaction and the publish-after-commit stay a convention of every service.
- **A query bus as well.** Symmetric, and what CQRS examples often show; returning a result
  from a handler needs `HandleTrait`, and nothing a query needs is added by the bus.
- **An event recorded by the entity and released after the flush.** Also removes the
  discipline, but needs custom infrastructure on Doctrine's events; the stamp does the same
  with the framework's own middleware.

## Consequences

- The pilot passes the tests of assigning, of `TaskAssigned` and of the email to the
  assignee unchanged. A new test fails the flush itself, after the event was dispatched:
  without the stamp the event would be on the queue; with it, it is not.
- Per use case: two classes instead of one, 36 lines of code instead of 26.
- Messenger wraps what a handler throws in `HandlerFailedException`, which nothing in
  Symfony unwraps: a refused assignment answered `500` instead of `422` or `409`.
  `Shared\Http\HandlerFailureListener` unwraps it before the error is rendered.
- An event released after the commit that cannot reach the broker surfaces as
  `DelayedMessageHandlingException`, and the request answered `500` after the change was
  saved. `Shared\Messaging\LogUnsentEventMiddleware` on `event.bus` logs it instead, the
  job `EventPublisher` does for the services; it goes once every service is a command.
- A command that dispatches another command runs it in a nested transaction, a savepoint.
- The controller relies on the identity map to render the assigned task. A handler that
  replaces the entity, or a command handled asynchronously, would need the controller to
  read the result back.
