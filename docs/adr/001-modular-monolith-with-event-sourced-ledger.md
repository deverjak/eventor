---
status: accepted
last-verified: 2026-10-24
---

# ADR 001 — Modular monolith with stateful aggregates and an event-sourced payment ledger

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/deptrac.yaml` with no violations, `backend/src/Payments/Domain/Ledger/FinancialState.php`, the migration test `tests/Integration/Migrations/AppendOnlyGrantsTest.php` and the scenario tests `S01`/`S02`. Full analysis of the options: [navrhy/01-smer-b-vs-c.md](../architecture/navrhy/01-smer-b-vs-c.md).

## Context

The assignment (V3, chapter 14, questions 1 and 5) leaves two choices open: the module boundaries, and "audit log vs. event sourcing". The forces that shape the architecture most:

- **Atomic capacity across levels.** Event, activity, variant and wave are all checked within one order (CAP-02, S01, S02). The invariant spans **many orders**.
- **Financial state derived from immutable payment events.** A manual intervention is a new event, never an overwrite (§4, PAY-08, S03, S04, S07, S11).
- **GDPR is a headline feature.** Personal data must be erasable or anonymisable while financial records are retained (PRV-04, retention classes).
- **Full audit** of changes, distinguishing system, team member and participant (AUD-01–04).
- The demo must be **a working product, not a prototype**, covering P0 and P1.

Four directions were considered:

- A: layered CRUD monolith.
- B: modular monolith with state plus a ledger.
- C: CQRS with event sourcing of order and participation.
- D: distributed services.

## Decision

Eventor is a **modular monolith**: one Symfony 8.x application (8.1 at the time of writing) on one PostgreSQL 17 database.

- **Modules.** There are twelve bounded contexts, each in `backend/src/<Module>/{Domain,Application,Infrastructure,UserInterface,Contract}`. Another module may import only `<Module>/Contract`. deptrac enforces this, and so does the `ModuleBoundaryPass` compiler pass inside the DI container.
- **Order, participation and every other aggregate are stateful** Doctrine entities with behaviour. Each state dimension is a Symfony Workflow state machine ([ADR 011](011-workflow-component-state-machines.md)).
- **The payment ledger is the only event-sourced part.**
  - `ledger_entry` is append-only.
  - `FinancialState` is a fold over the order's entries.
  - `order_finance` holds a cache of that fold, recomputed in the same transaction that appends the entry.
- **History outside money lives in an append-only `audit_entry`.** The application DB role gets only `INSERT` and `SELECT` on it, and a test enforces this.
- **Cross-module reactions go through the transactional outbox,** with one delivery row per subscriber ([ADR 003](003-transactional-outbox-per-subscriber-delivery.md)). Only Registration (with its capacity part) and Legal share a synchronous transaction. The one documented exception is cash taken at check-in: CheckIn calls the Payments module API `CashDesk::recordCash` in the same transaction, because CHK-08 requires the financial state to change immediately at the desk.

## Why B and not C (event-sourced order and participation)

1. **Capacity does not fit event sourcing cleanly.** ES protects invariants inside one stream. Capacity spans many orders and two to four levels at once, and every C option has a serious cost:
   - A per-bucket aggregate means appending to several streams in one transaction. Those streams run hot, so a registration rush becomes a cascade of retries.
   - A reservation saga makes S02 ("never a partially taken seat") only eventually true.
   - A hybrid with a state table puts capacity exactly where B has it anyway.
   - Dynamic Consistency Boundary is still experimental in PHP.

   In B atomicity is trivial: one transaction with one conditional `UPDATE` per bucket in a deterministic order, backed by a `CHECK` constraint ([ADR 002](002-capacity-buckets-conditional-update.md)).
2. **GDPR is cheap in B and expensive in C.** Immutable events that contain personal data and free-form answers need crypto-shredding: a key per person, where erasure means deleting the key. Every projection and export then has to decrypt, and every read model needs an "erased person" state. In B erasure is an in-place anonymisation. The ledger and the audit log carry no personal data; personal diffs go to `audit_pii`, which has a shorter retention class.
3. **Event sourcing pays off exactly where B uses it.** Money needs immutability, compensating entries, derived state and idempotency. The ledger also holds no personal data and no cross-aggregate invariants, so it gets the benefits of ES without the costs above.
4. **One audit mechanism.** C would keep streams for orders plus an audit log for Catalog, Legal and Identity. B has one audit log plus the ledger.
5. **Strong read consistency.** Admins, check-in staff and finance immediately see what was written, with no inline projections, position tracking or rebuilds.
6. **Deliverability.** Estimates for P0 and P1: about 8–10 weeks in B, versus 12–14 weeks in C with a high risk of an unfinished demo.
7. **Symfony as an architecture tool.** In B the outbox, the buses, Workflow, Scheduler, value resolvers and compiler passes are all our own code on the container. In C an ES library (Patchlevel) would take over a large part of the wiring.

## Consequences

**Positive**
- Atomic capacity, simple GDPR erasure, one audit trail and strong consistency.
- Lower delivery risk.
- Payments talks to the rest only through the outbox and its port, which makes it the first candidate for extraction into a separate service if ever needed.

**Negative**
- "What did the order look like on Tuesday at 14:00" is reconstructed from the audit log rather than replayed. Financial state is the exception: it can be replayed.
- The rule "every change writes audit and outbox in the same transaction" must be enforced. `AuditMiddleware` on `command.bus` does that, together with a per-command integration test that asserts the expected outbox event.
- Behaviour-rich entities need discipline: no public setters, and Doctrine attributes on aggregates are metadata only: Domain never calls the `EntityManager`.

## Alternatives considered

- **A — layered monolith.** Rules scatter across services and Doctrine listeners, and the outbox and ledger end up bolted on. It is weakest exactly on S03–S09.
- **C — CQRS + ES of order and participation.** See "Why B and not C".
- **B+ — ES order stream with no personal data in events, capacity kept stateful.** Feasible, but about 2–3 weeks more and two audit mechanisms. Kept as a possible later step if temporal queries over orders become a real need.
- **D — distributed services.** S02 and S13 would require sagas with compensations, and the operational cost does not fit a single demo instance.

## Verification

- Concurrency scenario tests `S01` (last seat, parallel processes) and `S02` (one order spanning a full activity and a free one).
- A migration test asserting the application DB role has no `UPDATE`, `DELETE` or `TRUNCATE` on `ledger_entry`, `audit_entry` and `confirmation`.
- deptrac in CI with zero allowed violations.
- An architecture test asserting that every command handler emits at least one outbox event or carries `#[ReadOnlyCommand]`.

## Related

[ADR 002](002-capacity-buckets-conditional-update.md), [ADR 003](003-transactional-outbox-per-subscriber-delivery.md), [ADR 011](011-workflow-component-state-machines.md) · [02 Modules and boundaries](../architecture/02-moduly-a-hranice/README.md), [05 State machines](../architecture/05-stavove-automaty/README.md) · Supersedes: None.
