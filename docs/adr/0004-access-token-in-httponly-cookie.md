# 0004. Carry the access token in an HttpOnly cookie, not the `Authorization` header

- Status: accepted
- Date: 2026-03-24
- Implemented in: #6, refined in #22

## Context

At first the login response returned the JWT in its body, and the client sent it back in
the `Authorization: Bearer` header. The client is a browser application, so the token had
to live in storage that JavaScript can read. Any XSS on the page could then read the token
and send it elsewhere, where it stays usable until it expires.

## Decision

The access token travels only in a cookie.

- `access_token`: `HttpOnly`, `SameSite=Strict`, `Secure` in production, path `/`, expiring
  with the token.
- `refresh_token` has the same flags, but its path is `/api/v1/auth`, so the browser sends
  it only to the endpoints that use it.
- Lexik reads the token from the cookie alone; the `Authorization` header extractor is
  disabled.
- CORS allows credentials, for the configured origin only.
- The auth endpoints (`/api/v1/auth`) run in their own firewall without the JWT
  authenticator (#22).

## Alternatives considered

- **`Authorization` header, with the token kept in JavaScript-readable storage.** The common
  SPA setup, and the one this replaced. Lost because the token is exposed to every script on
  the page.

## Consequences

- JavaScript cannot read the token, so XSS cannot steal it. XSS can still send requests
  as the user while the page is open — the cookie protects the token, not the session.
- The browser now attaches credentials automatically, which opens the door to CSRF.
  `SameSite=Strict` closes it instead of CSRF tokens: the cookies are not sent on requests
  initiated by another site. The protection relies on the browser, and a compromised
  subdomain of the same site counts as same-site.
- A cookie is sent whether or not the endpoint needs it. Before #22, an invalid or
  expired `access_token` cookie made the JWT authenticator reject login, register, refresh
  and logout — the very requests a user makes to recover from an expired token. The
  separate `auth` firewall fixed it; logout still blocklists a valid access token, because
  Lexik's logout listener reads the cookie from the request itself.
- Non-browser clients — a mobile app, a CLI — would have to manage cookies. Supporting them
  means re-enabling the header extractor, and deciding how those clients get CSRF-free
  guarantees of their own.
