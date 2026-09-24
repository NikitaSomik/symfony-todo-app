# 0002. Build authentication inside the application instead of using an identity provider

- Status: accepted
- Date: 2026-03-03
- Implemented in: #5

## Context

The API needs user accounts: registration, login with email and password, and a way to
tell which user a request comes from. The one client is a first-party browser application.

Authentication can be owned by the application, on top of Symfony Security, or delegated to
an identity provider speaking OAuth 2.0 / OpenID Connect — self-hosted like Keycloak, or a
hosted service like Auth0. The provider then runs login and stores credentials, and the API
only validates the tokens the provider issues.

## Decision

Authentication lives in the `Auth` module: users and password hashes in our database,
login through Symfony's `json_login`, access tokens issued by Lexik JWT, refresh tokens and
revocation implemented here ([0003](0003-jwt-signed-with-hs256.md),
[0004](0004-access-token-in-httponly-cookie.md), [0005](0005-revocable-sessions.md)).

The reason is the goal of this stage, not a verdict on the alternatives: to build and
understand the whole mechanism — password storage, token issuing and validation, sessions,
revocation, abuse protection — rather than configure a product that hides it. The project
avoids API Platform for the same reason.

## Alternatives considered

None of these was rejected. They are deferred, and which one — if any — comes next is
still open.

- **Keycloak.** A self-hosted identity server: SSO, MFA, social login and user management
  out of the box, at the price of a second system to run, secure and upgrade.
- **Auth0.** The same features as a hosted service: nothing to operate, in exchange for
  an external dependency on every login, and the vendor's pricing and limits.
- **Single sign-on with an existing account** — Google, Microsoft Entra ID, or a company's
  own identity provider. Users keep no password with us and log in once for every
  application that trusts the same provider. The API accepts identities it does not own,
  over OpenID Connect or SAML, either directly or through Keycloak or Auth0 as a broker.

## Consequences

- Every security-sensitive piece is ours to get right and to keep right: password hashing,
  login and registration throttling, token storage, revocation. The later records and PRs
  are that cost being paid.
- Features a provider has out of the box do not exist here: password reset, email
  verification, multi-factor authentication, social login, single sign-on.
- Revisit when one of those becomes a requirement — above all SSO across several
  applications, MFA, customers bringing their own identity provider, or third-party
  applications that need to act on a user's behalf, which is what OAuth 2.0 delegation
  exists for. Moving to OpenID Connect would replace login and token issuing; the API
  would validate the provider's tokens against its published keys, which also retires
  [0003](0003-jwt-signed-with-hs256.md).
- The move stays cheap to start: tokens are validated in one place, by Lexik behind the
  Symfony firewall, and no other module knows how a user was authenticated.
