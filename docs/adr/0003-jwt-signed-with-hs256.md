# 0003. Sign access tokens with HS256 and a shared secret

- Status: accepted
- Date: 2026-03-03
- Implemented in: #5

## Context

Access tokens are JWTs issued by Lexik JWT. A JWT is signed either symmetrically (HMAC —
one secret both signs and verifies) or asymmetrically (RSA or ECDSA — a private key signs,
a public key verifies). The choice decides who can mint tokens and what key material has
to be managed.

One Symfony application both issues and verifies every token. No other service reads them.

## Decision

Tokens are signed with HS256. The secret is the `JWT_SECRET` environment variable; there
are no key files and no passphrase.

## Alternatives considered

- **RS256.** Its advantage is the split between the signer and the verifiers: a service
  holding only the public key can check a token but cannot forge one. With a single
  application there is nobody to split from, so RS256 would add a key pair to generate,
  store, protect with a passphrase and rotate, for no gain.

## Consequences

- Whoever knows the secret can mint a token for any user. The secret must exist only in
  the application's environment, never in the repository or the logs.
- A weak secret fails closed. `lcobucci/jwt`, which Lexik signs with, refuses an HMAC key
  shorter than 256 bits, on signing and on verification alike. Verified with the 48-bit
  placeholder in the committed `.env`: encoding throws `InvalidKeyProvided`. The failure
  surfaces late, though: the application boots normally and only the first login fails. A
  startup check in `prod` would move the failure to the deploy.
- Rotating the secret invalidates every access token at once. That costs one silent
  refresh per user: access tokens live 15 minutes, and refresh tokens are opaque database
  records that do not depend on the secret (see [0005](0005-revocable-sessions.md)).
- Revisit when a second party has to verify tokens — another service, an API gateway, an
  external integration — or when keys must rotate with an overlap period. Both call for an
  asymmetric algorithm and a published public key.
