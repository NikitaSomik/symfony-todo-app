# 0005. Make sessions revocable: short access tokens, rotating refresh tokens, a logout blocklist

- Status: accepted
- Date: 2026-03-24
- Implemented in: #6, refined in #17 and #21

## Context

A JWT is valid until it expires; the server keeps nothing it could delete. A long-lived
access token therefore means a stolen token works for as long as it lives, and "log out"
only deletes the client's copy. A short-lived one alone forces the user to log in again
every few minutes.

## Decision

- **Access token:** a JWT that lives 15 minutes.
- **Refresh token:** 256 random bits, opaque rather than a JWT, valid for 30 days, stored in
  the database. It is single-use: `POST /api/v1/auth/refresh` deletes it and issues a new
  pair in the same transaction.
- **Only a SHA-256 hash of a refresh token is stored** (#21), and lookups go by the hash.
- **Logout revokes every refresh session of the user**, not only the current one. The user
  is identified by the refresh token cookie, not by the access token, which may already
  have expired — before #17 an expired access token made logout revoke nothing.
- **Logout also blocklists the presented access token** until it expires: Lexik's
  `blocklist_token` stores its `jti` in Redis and checks every request against it.

## Alternatives considered

- **`gesdinet/jwt-refresh-token-bundle`**, the usual Symfony choice. Its rotation deletes the
  old token and saves the new one in two separate flushes, outside a transaction, so a
  failure between them leaves the user with no session. Here both happen in one
  transaction: a failure rolls back and the old token still works.
- **Plain refresh tokens in the database** — the original design, replaced in #21. A leaked
  table, backup or replica would hand out live sessions.
- **A password hash (bcrypt, Argon2) for refresh tokens.** Deliberately slow, so it would
  cost CPU time on every refresh — and buy nothing: slowness defends guessable secrets, and
  256 random bits cannot be brute-forced. A salted hash would also make the lookup
  impossible to index.

## Consequences

- A session is revoked within at most 15 minutes, and immediately for the access token
  presented at logout.
- Every authenticated request reads Redis for the blocklist. Redis has become part of
  authentication, not just a cache.
- Logging out on one device ends the sessions on all of them.
- **Rotation does not detect theft.** If an attacker uses a stolen refresh token before the
  user does, the attacker gets the new pair; the user's next refresh fails and they log
  in again, while the attacker's chain continues. The user's next logout ends it, because
  logout revokes every session. Detecting the reuse of a spent token and revoking the whole
  token family (RFC 9700) is planned — see the roadmap.
- **Two concurrent refreshes with the same token can both succeed.** When both requests
  find the token before either deletes it, one session forks into two live refresh tokens. Reuse
  detection has to claim a token atomically, or it will read this race as theft and log
  the user out.
- `gesdinet/jwt-refresh-token-bundle` 3.0 (August 2026) added token families, reuse
  detection and hashed storage. Weigh it again before building reuse detection here.
