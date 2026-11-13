# 0034. Meetings run in a separate Go service, Meet, behind the same origin

- Status: proposed
- Date: YYYY-MM-DD — when the decision reached `main`, taken from git history
- Implemented in: #PR

## Context

Video meetings are next on the roadmap: members of a workspace start a call and join it
from a link. The roadmap first had them as a module of this monolith, with LiveKit behind
an interface. The service around the media is to be written in Go, a choice already made:
it is the language LiveKit itself and most of the WebRTC tooling are written in, and the
project means to show a second service beside the monolith.

The media never passes through our code. A selective forwarding unit carries the audio and
video between the browsers; what we write decides who may start a meeting, who may join,
how many and for how long, and keeps track of the meetings that ran.

## Decision

- **Meetings live in their own service, Meet, written in Go, in its own repository**
  (`go-meet-service`). The rest of the application stays one modular monolith
  ([0001](0001-feature-based-modular-monolith.md) stands).
- **LiveKit carries the media.** Meet creates rooms, issues LiveKit access tokens and
  learns from LiveKit's signed webhooks when a room has finished. How it does so is
  decided in Meet's own records.
- **The first version is a call inside a workspace.** Only members of the workspace join;
  there are no guests, no schedule, no recording, and a meeting is not linked to a task.
- **Meet is public, behind the same nginx and origin**, under `/api/v1/meetings`. The
  `access_token` cookie (`SameSite=Strict`, path `/`) reaches it as it reaches the
  monolith, so the browser needs no second login and no CORS.
- **It speaks the same API as the monolith:** JSON:API responses, plain JSON request bodies
  ([0008](0008-json-api-responses-plain-json-requests.md)), and a meeting outside the
  user's reach answers `404` ([0011](0011-foreign-task-answers-404.md)).
- **Meet keeps its data in its own database**, `meet`, on the same PostgreSQL server, under
  its own user with no grant on the monolith's tables. It refers to users and workspaces
  by id, without a foreign key, as modules do ([0018](0018-modules-meet-through-contracts.md)).
- **Meet verifies the user's access token itself** ([0035](0035-access-tokens-signed-with-rs256.md))
  **and asks the monolith what the user may do** ([0036](0036-meet-asks-the-monolith-for-permissions-and-limits.md)).
- **A decision that changes the monolith is recorded here; one inside Meet is recorded in
  Meet's repository.**

## Alternatives considered

- **A module of this monolith, in PHP, with LiveKit behind a port.** Fewest moving parts:
  one deploy, permissions and limits by an in-process contract. Lost because the goal of
  this step is a second service in Go, and the decisions a service boundary forces —
  who verifies tokens, how a service learns permissions — are what it is meant to show.
- **Meet internal, behind the monolith.** The browser talks only to the monolith, which
  checks the permission and calls Meet with a service token. Authentication stays in one
  place, but the monolith proxies every endpoint and the Go service shrinks to a client of
  LiveKit.
- **Meet public, with the permissions carried in the access token.** No call back to the
  monolith, but a token holds a user's permissions for every workspace, grows with them,
  and keeps a removed member's rights for its 15 minutes.
- **The code in a directory of this repository, `services/meet/`.** One pull request could
  change both sides of the contract, but every Go change would run under this repository's
  PHP rules, hooks and checks, and its CI would build both.
- **Media peer to peer, Meet only signalling; or an SFU of our own on Pion.** A mesh breaks
  at four or five participants; an SFU of our own is months of work on simulcast,
  reconnection and TURN before the first meeting.
- **Guests by link, meetings on a schedule, recording.** Each adds a second kind of
  identity, a calendar or storage. Left for when a team asks for it.

## Consequences

- Two repositories and two deploys. A change to the contract between them — the token, the
  internal API — is two pull requests, released monolith first.
- Meet depends on the monolith at runtime: without it nobody signs in, and Meet cannot ask
  for permissions, so it refuses to start or join a meeting rather than guess.
- nginx routes by prefix; a path under `/api/v1/meetings` never reaches PHP.
- LiveKit is a new container, with ports for media and, outside a local network, a TURN
  server.
- Revisit when Meet needs guests, a separate domain or its own deploy cadence.
