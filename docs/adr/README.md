# Architecture Decision Records

Each record captures one decision: the context that forced it, what was chosen, the
alternatives that lost and why, and what the choice costs.

The format is Michael Nygard's original ADR, extended with the "considered options" section
from [MADR](https://adr.github.io/madr/).

A decision that was later revised is not deleted. It is marked as superseded and links to
the record that replaced it, so the history of how the design got here stays readable. A
decision withdrawn without a replacement is marked as deprecated.

Records 0001–0005 were written retroactively, in September 2026, from the code, the git
history and working notes. Their "Alternatives considered" sections list only the options
that were actually weighed — as the history, the notes or the author's own account show.

Dates come from git history, and every record links the pull request that implemented it.
New records follow [the template](template.md).

Statuses:

- **proposed** — written down, not implemented yet
- **accepted** — in force, matches the code
- **deprecated** — withdrawn, nothing replaced it
- **superseded** — replaced by the linked record

| # | Decision | Status | Date |
|---|---|---|---|
| [0001](0001-feature-based-modular-monolith.md) | Organise the code as a feature-based modular monolith | accepted | 2026-02-27 |
| [0002](0002-authentication-inside-the-application.md) | Build authentication inside the application instead of using an identity provider | accepted | 2026-03-03 |
| [0003](0003-jwt-signed-with-hs256.md) | Sign access tokens with HS256 and a shared secret | accepted | 2026-03-03 |
| [0004](0004-access-token-in-httponly-cookie.md) | Carry the access token in an HttpOnly cookie, not the `Authorization` header | accepted | 2026-03-03 |
| [0005](0005-revocable-sessions.md) | Make sessions revocable: short access tokens, rotating refresh tokens, a logout blocklist | accepted | 2026-03-24 |
