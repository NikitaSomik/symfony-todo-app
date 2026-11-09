---
name: grill-with-docs
description: Settle a design for symfony-todo-app with Nikita before any code - interview him in rounds until no decision is open, then write each decision worth keeping as an ADR in the project's template. Use when Nikita wants to discuss a design, an architecture, a new module or a rule before it is built, or says "grill me".
argument-hint: "[the plan, design or question to discuss]"
---

# Grill with docs

Topic: $ARGUMENTS

Settle the design with Nikita, then keep what was decided in `docs/adr/`. This skill writes
no code. When the design is settled and Nikita wants it built, the `implement` skill takes
it from there, with the ADRs as its input.

## 1. Learn what is already decided

Before the first question, read `docs/adr/README.md`, the ADRs the topic touches, the
README's rules, `docs/roadmap.md` and the code of the modules involved.

Facts come from the code and the docs, never from Nikita: which module owns what, how the
neighbouring feature does it, what the tables hold, what Stripe or Symfony do. For a sweep
across several modules, send an Explore subagent. Nikita is asked only for decisions.

## 2. Interview in rounds

The decisions form a tree: some can be made only once others are settled. A round asks
every decision whose prerequisites are settled, as one numbered list, then waits for
Nikita's answers.

Each question gives:

- the decision, in one line;
- the options, each with its cost: what it takes to build, migrations, load, what it
  closes off later;
- the recommended option and why, worded so that "yes" accepts it.

"Not needed now" is an option when the signal that would make it needed can be named.

```
**Q1. Who may change the workspace's plan?**
- A. The owner only. Cost: nothing new.
- B. The owner and members. Cost: a new permission in the role table, its tests.
➡ A: money stays with one person until a team asks for more. Yes?
```

While answers come in:

- Check each answer against the accepted ADRs. When it contradicts one, say which, and ask
  Nikita whether the answer changes or the old ADR is superseded.
- Stress-test with concrete cases: two requests at once, a webhook twice, late or out of
  order, a user from another workspace, a viewer, an empty parameter, a deploy halfway
  through a migration.
- Use the words of [GLOSSARY.md](../../../GLOSSARY.md). When Nikita and the code name the
  same thing differently, or a new term comes up, ask which name wins, and write it into
  the glossary right then: the term, one or two sentences on what it is, the words to
  avoid.

Rounds go on until nothing is open. Then sum up the design in a few lines and ask Nikita to
confirm it. Nothing is written before he does.

## 3. Write the ADRs

Propose which decisions get an ADR. One does when it is hard to reverse, would surprise a
reader without its context, or came out of a real trade-off. A smaller decision goes into
an existing ADR or nowhere. One ADR holds one decision, so that a later change supersedes
only what it changes.

Each ADR follows `docs/adr/template.md` and the rules in `docs/adr/README.md`, like the
records around it:

- File `docs/adr/NNNN-the-decision-as-a-phrase.md`, numbered after the highest one; the
  title is the decision as a sentence.
- `Status: proposed`. `Date` and `Implemented in` stay as placeholders until it reaches
  `main`: the date comes from git history, the PR number from the PR.
- Context: what forced the decision. Decision: stated as rules. Alternatives considered:
  only the options weighed in this discussion, each with why it lost. Consequences: what it
  costs, what it makes harder, and the signal that would make us revisit it.
- An ADR it replaces gets `Status: superseded by [NNNN](NNNN-....md)` and is otherwise left
  as it is.
- The record gets a row in the table of `docs/adr/README.md`.
- A new rule is linked from the README's rules and, if the code does not show it at a
  glance, from the rules in `CLAUDE.md`. `docs/roadmap.md` and `docs/architecture.md` change
  when the design changes them.

Show the ADRs as a diff. They are drafts until Nikita says they are right. Commit, push and
the pull request follow `CLAUDE.md`: only when Nikita says so.

## 4. Hand over

End with the ADRs written, the questions left open on purpose (each with its signal), and
the next step, usually `implement` with the ADR.
