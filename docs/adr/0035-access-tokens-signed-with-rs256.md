# 0035. Access tokens are signed with RS256, so that Meet can verify them without minting them

- Status: proposed
- Date: YYYY-MM-DD — when the decision reached `main`, taken from git history
- Implemented in: #PR

## Context

Access tokens are signed with HS256 and a shared secret
([0003](0003-jwt-signed-with-hs256.md)), which fits one application that both issues and
verifies them. That record names its own signal to revisit: a second party that has to
verify tokens. Meet is that party ([0034](0034-meetings-run-in-a-separate-go-service.md)):
it receives the `access_token` cookie and must know which user it names (`sub`, the user's
id — [0027](0027-the-access-token-names-the-user-by-id.md)).

## Decision

- **The monolith signs access tokens with RS256.** The private key exists only in the
  monolith's environment; the public key is given to every service that verifies tokens.
- **Meet verifies a token with the public key alone**: the signature, the expiry, and the
  user id in `sub`. It cannot issue one.
- **Meet does not read the blocklist of logged-out tokens.** A token logged out stays good
  in Meet until it expires, at most 15 minutes ([0005](0005-revocable-sessions.md)); every
  start and every join still asks the monolith whether the user is a member
  ([0036](0036-meet-asks-the-monolith-for-permissions-and-limits.md)).
- **Logging out, or leaving the workspace, does not end a meeting already joined.** It only
  refuses the next join.
- **Refresh tokens do not change.** They are opaque database records and never leave the
  monolith.

When this record is implemented, [0003](0003-jwt-signed-with-hs256.md) is superseded by it.

## Alternatives considered

- **Give Meet the HS256 secret.** Nothing to change in the monolith, but whoever holds the
  secret can mint a token for any user, so a flaw in Meet would become a way into the
  monolith.
- **Meet asks the monolith who a token belongs to, on every request.** Logout would take
  effect at once, but every request to Meet would wait on a call to the monolith, for a
  check the signature already gives.
- **Meet reads Lexik's blocklist in Redis.** Logout would take effect at once in Meet too,
  but Meet would depend on how a PHP library names its keys.
- **Removing a member from a meeting in progress**, from an event the monolith publishes.
  It needs a channel of events between the services, which today carry PHP-serialized
  messages Go cannot read. Left until it is asked for.

## Consequences

- A key pair to generate, store and rotate instead of one secret. Rotation with an overlap
  needs the old public key kept beside the new one for 15 minutes.
- Switching the algorithm invalidates every live access token once. It costs one silent
  refresh per user, as rotating the secret did.
- A removed member stays in a meeting already joined, and a logged-out token opens Meet's
  read endpoints for up to 15 minutes.
- Revisit when a removed member must leave a meeting at once, or when more services verify
  tokens and the public key should be published (JWKS) rather than handed out.
