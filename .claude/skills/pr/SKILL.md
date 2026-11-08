---
name: pr
description: Write the title and description of a pull request in symfony-todo-app - why, what changes, evidence, merge danger, what is not here, docs. Use whenever a pull request is opened or its description is rewritten.
---

# Pull request description

Written for Nikita, who decides on the merge from it. Lead with what he needs for that
decision; leave out the story of how the change was made.

- **Title**: the commit line, `type(module): lowercase imperative summary`.
- **Opening**: one or two sentences on why the change exists, with the ADR or issue it
  follows.

## What changes

Behaviour first: what a client or a user can now do or gets refused, with the status codes.
Then the files by module. When a flow changes across classes or modules, a small Mermaid
diagram or call tree, if it reads faster than prose.

## Evidence

What shows the change works, as output, not as a claim:

- A test that failed before the change and passes after: the command and the last lines.
- The end of `make verify`.
- For an API change, one request and its response.

## Merge danger

- **Two-way door**: reverting the merge leaves nothing behind. Say so in one line.
- **One-way door**: a migration that drops or rewrites data, an API contract clients rely
  on, a message already sent (an email, a call to Stripe), a changed event in `Contract/`.
  Name it and say how it would be undone.
- **Blast radius**: the modules, endpoints and tables it touches.

## Not here

What was left out on purpose, and where it goes next.

## Docs

The ADR, the README rule line, `docs/openapi.json`, the roadmap, or "none".

## Rules

- English. No Claude attribution, no "Generated with" line, no session link.
- Nikita is neither assignee nor reviewer.
- If the repository has `.github/pull_request_template.md`, its headings come first.
