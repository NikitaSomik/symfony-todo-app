# 0026. A new password is checked against known breaches, and the check never blocks a sign-up

- Status: accepted
- Date: 2026-10-13
- Implemented in: #94

## Context

Passwords are rarely guessed; they are reused. A pair leaked by another site is tried here
in bulk, and login throttling slows that down without stopping it. Registration accepted
any password of 8 to 100 characters, `password123` included.

NIST SP 800-63B rev 4 (2025) requires a new password to be compared against a list of
compromised and commonly used ones, the user to be told why it was refused, and forbids
composition rules (a digit, a capital, a symbol).

## Decision

- **`#[Assert\NotCompromisedPassword]` on the registration password.** Symfony's validator
  asks Have I Been Pwned's range API — free, no key — with k-anonymity: only the first 5
  characters of the password's SHA-1 leave the server, and the match is made here. The
  request goes from the server, so neither the user's IP nor any account data reaches it.
- **`skipOnError: true`:** when the lookup fails, the password is accepted. A sign-up
  matters more than this check, and an outage there must not become one here. The HTTP
  client gives up after 2 seconds of silence or 3 in all, instead of PHP's 60.
- **No composition rules**, so no `PasswordStrength`: it scores `Password123!` as strong.
- Tests answer the range API with `PwnedPasswordsStub` (the `http_client` mock response
  factory), so the real validator runs and no test reaches the network.

## Alternatives considered

- **`PasswordStrength` instead.** Offline, but it measures the shape of a password, not
  whether it is already in an attacker's list.
- **A local copy of the Pwned Passwords list** (`not_compromised_password.endpoint`). No
  third party at all, at the cost of hosting and regularly refreshing a dataset of several
  gigabytes. Worth it only if a data protection review rules out the request.
- **Failing closed** (`skipOnError: false`). Every outage of an outside service would stop
  sign-ups.

## Consequences

- Only new passwords are checked. An account created before, or a password that leaks
  later, is not — that needs a check at login or a password change, neither built.
- A password that has not leaked yet passes however weak; length is the other defence.
- **The minimum length stays at 8.** NIST asks for 15 when the password is the only
  factor, as it is here. Raising it is a product decision left for when a second factor
  or passkeys arrive.
- `symfony/http-client` joins the dependencies.
