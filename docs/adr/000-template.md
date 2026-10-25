---
status: template
last-verified: 2026-10-24
---

# ADR NNN — Decision title

**Status:** Template, not a decision. Copy it to the next numbered file and never make the template itself authoritative.

Every ADR has a `Status:` line directly under its title. It holds `accepted <date>`, `superseded by ADR NNN` or `reverted`, followed by the evidence that proves the current state: a file, test, migration or config key. Until code exists, write `design only, no code yet` and name the artefact that will prove it.

## Context

The concrete problem, the forces and the constraints. Keep verified behaviour (code, an external contract) apart from intent. Cite the requirement IDs from the assignment (`REG-01`, `S01`).

## Decision

One decision and its scope, stated decisively with concrete names (classes, tables, config keys). Record the owner and the acceptance date.

## Consequences

Positive, negative, operational, security and migration effects.

## Alternatives considered

Each meaningful alternative and why it was rejected.

## Verification

How the decision is enforced and proved: tests, static analysis (deptrac, PHPStan), DB constraints, CI checks.

## Related

ADR numbers and book chapters (`../architecture/NN-…/README.md`). Supersedes or superseded by: explicit ADR numbers or `None`.
