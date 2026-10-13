# 0027. The access token names the user by id, not by email

- Status: accepted
- Date: 2026-10-13
- Implemented in: #96

## Context

The access token carried the user's email in a `username` claim, and every request found
the user by it. Changing an email is not built, but an email is meant to change: a token
issued before the change would stop finding its user, and once the old address belonged
to someone else, would find theirs. The payload of a JWT is only encoded, so the email
could also be read wherever the token travels.

## Decision

- **`User::getUserIdentifier()` returns the id.** It never changes and says nothing about
  the person.
- **The claim is `sub`**, the one RFC 7519 defines for the subject of a token. The email
  is no longer in the token.
- **Two user providers**, because the two firewalls find a user by different keys:
  `users_by_email` for signing in, through the repository that normalises the email, and
  `users_by_id` for the access token, by primary key.

## Alternatives considered

- **The id in the existing `username` claim.** A token issued before the deploy would
  bring an email to a lookup by an integer key, and PostgreSQL would answer the request
  with a `500`. Without `sub`, such a token is an invalid payload: `401`, and the client
  refreshes as it does for an expired one.
- **The `lexik_jwt` provider, which builds the user from the token without a query.** It
  saves a lookup by primary key, but a deleted user would stay signed in until the token
  expires.
- **UUIDs for users**, as tasks ([0010](0010-task-identity-uuidv7.md)) and sessions
  ([0025](0025-refresh-token-families-and-reuse-detection.md)) have. A sequential id now
  leaves the server in every token, and it tells how many users there are — nothing that
  grants access. Changing the primary key touches a foreign key in every module. If the
  count must be hidden, a public UUID beside the integer key does it without that.

## Consequences

- A user can change their email without signing out, once changing it is built.
- For up to 15 minutes after the deploy, tokens of the former format are refused and the
  `security` log records them as `invalid_payload`. The refresh token knows nothing of the
  email, so clients only refresh.
- The email no longer reaches the log of every authenticated request: Symfony's
  `Authenticator successful!` record names the user by id, and the production buffer that
  an error flushes carries no email.
- `getUserIdentifier()` is no longer what a user types to sign in. Code that needs the
  email asks for `getEmail()`, and a feature that loads a user by identifier — a login
  link, impersonation, remember-me — uses `users_by_id`.
