# Glossary

The words of the task tracker, as code, ADRs, the API and conversations use them. A name in
the code takes its words from here; a word under _Avoid_ is not used for that thing. A new
term is added here when it is settled, in the same pull request.

## People and workspaces

**User**:
Someone with an account, who signs in with their email.
_Avoid_: account, customer, identifier (for the email)

**Workspace**:
A group of people who share tasks; every user starts with a personal one.
_Avoid_: team, organisation, project

**Personal workspace**:
The workspace created for a user at registration, marked as such.
_Avoid_: private workspace, default workspace

**Member**:
A user in a workspace, with one role there.
_Avoid_: participant, collaborator

**Role**:
Owner, member or viewer: a named set of permissions in one workspace.
_Avoid_: access level, group

**Permission**:
Something code may ask whether a member can do: `view`, `manage_workspace`, `work_on_tasks`, `delete_tasks`.
_Avoid_: right, privilege, capability

**Actor**:
The user who made a change, as an audit entry and an event name them.
_Avoid_: author, initiator

## Tasks

**Task**:
A piece of work in a workspace, moved through its statuses by transitions.
_Avoid_: ticket, issue, todo, item

**Status**:
Where a task stands: to do, in progress, blocked, in review, completed or cancelled.
_Avoid_: state, stage

**Transition**:
A named move between statuses (start, block, submit for review, complete, cancel…); the only way a status changes.
_Avoid_: status update, state change

**Finished**:
A task that is completed or cancelled; it cannot move again. The others are unfinished.
_Avoid_: closed, done, final (for a task)

**Assignee**:
The one member who works on a task; always someone who may work on tasks.
_Avoid_: owner (of a task), responsible, executor

**Cancellation reason** / **block reason**:
The explanation a task keeps when it is cancelled or blocked.
_Avoid_: comment, note

## History

**Audit log**:
The record of who changed what, from which value to which, and when, written with the change.
_Avoid_: activity log, history, journal

**Audit entry**:
One row of the audit log.
_Avoid_: activity, log line

**Fact**:
Something that happened in a module, announced to the others after the commit; its class is named in the past tense.
_Avoid_: notification, message (for the fact itself)

## Billing

**Plan**:
Free, Team or Business: how many members a workspace may have.
_Avoid_: tier (in code), package, product

**Plan limit**:
The number of members a plan allows, and the number of team workspaces on Free.
_Avoid_: quota, cap, seat limit

**Subscription**:
A workspace's paid plan, as Stripe holds it and we keep a copy.
_Avoid_: membership, contract

**Payer**:
The one who pays for a workspace: its customer in Stripe.
_Avoid_: buyer (for the record), billing account

## Working on the project

These name how the project is built, not the task tracker it is.

**Ticket**:
One future pull request, cut by `to-tickets` and built by `implement`; never a Task.
_Avoid_: task, issue, story (for this)

**Spec**:
The design of work that shows a sign in `to-spec`, such as more than one ticket, written after
a grill: the decisions, the flow, the seams to test at. Its tickets name it.
_Avoid_: epic, PRD
