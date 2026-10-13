# 0025. A sign-in is a family of refresh tokens; a spent token presented again revokes it

- Status: accepted
- Date: 2026-10-13
- Implemented in: #93

## Context

Refresh tokens rotate on every use ([0005](0005-revocable-sessions.md)), but the spent one
was deleted, so nothing could tell a stolen copy from an unknown token. An attacker who
refreshed first kept a session for up to 30 days; the user only saw a `401` that looked
like an ordinary sign-out. Logout ended every session of the user, because there was no
notion of a session to end on its own.

RFC 9700 (OAuth 2.0 Security BCP, 2025) asks clients that cannot bind a token to a key — a
browser is one — to rotate refresh tokens and detect the reuse of a spent one. Okta and
Auth0 implement it the same way: a token family per sign-in, revoked whole on reuse, with
a short window in which the previous token still works.

## Decision

- **A sign-in starts a family** (`refresh_token_families`): every token rotated from it
  belongs to it. The family is the session of one device.
- **A spent token is kept and marked `used_at`**, not deleted. Exchanging a token locks it
  and its family (`SELECT … FOR UPDATE`, #91), so two requests decide one after the other.
- **A spent token presented within 30 seconds of its use is served** another token in the
  same family. Tabs of one browser share the cookie and refresh together; a client whose
  response was lost retries with the token it still holds. Neither is a theft.
- **Presented later, it revokes the family** and answers `401`, like any invalid token,
  so the client learns nothing. A `warning` on the `security` channel records the user,
  the family and the IP. The revocation is committed before the `401` is thrown.
- **A family lives 90 days from the sign-in**, however active; a token lives 30 days
  without use and never past its family.
- **Logout revokes the family of the presented token** — this device only.
- **A family is identified by a UUIDv7** created in the application, unlike the integer
  ids of users and tokens: it is the one that will leave the server — in the security log
  today, and as the id of a session a user lists and ends on their own once that is built.
  A sequential id would show how many sign-ins there were and invite guessing.
- Revoked and expired families are deleted, with their tokens, by
  `app:auth:purge-expired-refresh-tokens`.
- **The migration that attaches tokens to families deletes the existing ones**: a token
  without a family has no place in the model, and inventing a family for each would keep
  sessions alive on a guess. Everyone signs in again once after the deploy.

The window and both lifetimes are parameters (`app.refresh_token_reuse_window`,
`app.refresh_token_family_ttl`, `app.refresh_token_ttl`).

## Alternatives considered

- **No window: any reuse revokes.** Auth0's default. It signs a user out for having two
  tabs, or for a flaky network. The window is the time a stolen copy goes unnoticed, so it
  stays short: the default of Okta, and above a client's timeout and retry.
- **A reused token answered `401` without revoking, inside the window.** Simpler, but
  nearly every client signs the user out on a `401` from refresh — the window would not
  help the case it exists for.
- **Revoking by deleting the family.** The cascade reaches the token rows a concurrent
  exchange of the same family may hold, and the two can deadlock. Marking the family
  conflicts with nothing.
- **`gesdinet/jwt-refresh-token-bundle` 3.0**, which added families and reuse detection.
  Its release notes mention no reuse window, and moving to it means replacing a tested
  implementation and its schema for one this project does not control.
- **Sender-constrained tokens (DPoP).** Stronger — a copied token is useless without the
  client's key — but needs key handling in the browser client. Revisit with a client that
  can hold a key.

## Consequences

- A stolen token is caught the first time both copies are used outside the window, and
  the session of that device ends for both. The user signs in again; the attacker cannot.
- Reuse within the window is not detected, and a request held up in the network for
  longer than the window signs the user out.
- Theft by a script on the page (XSS) is not covered: the script acts through the live
  cookie and never presents a spent token.
- Spent tokens stay in the database until they expire or their session ends — at most one
  row per refresh. A spent token presented after its own expiry is refused without revoking
  the family: by then it is 30 days old.
- Logging out on one device no longer ends the others. "Sign out everywhere" is not built.
