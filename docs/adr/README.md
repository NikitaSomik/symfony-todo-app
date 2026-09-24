# Architecture Decision Records

Each record captures one decision: the context that forced it, what was chosen, the
alternatives that lost and why, and what the choice costs.

The format is Michael Nygard's original ADR, extended with the "considered options" section
from [MADR](https://adr.github.io/madr/).

A decision that was later revised is not deleted. It is marked as superseded and links to
the record that replaced it, so the history of how the design got here stays readable. A
decision withdrawn without a replacement is marked as deprecated.

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
