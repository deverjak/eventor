---
status: accepted
last-verified: 2026-10-24
---

# ADR 011 — One Symfony Workflow state machine per state dimension

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/config/packages/workflow.yaml`, the `WorkflowAuditSubscriber` and `WorkflowDomainEventSubscriber`, the dumped graphs in `docs/architecture/05-stavove-automaty/img/`, and `WorkflowDefinitionsTest`.

## Context

Assignment chapter 5 requires: "states are separate dimensions; they must not be merged into one field". The dimensions are:

- event publication and registration state (on the event and on each activity, independently),
- participation state,
- the derived financial state of an order,
- check-in,
- legal confirmation,
- message delivery.

Transitions outside automation are always audited (chapter 5.3). Some transitions depend on configuration, for example auto-restore after a late payment (5.5) and whether the check-in role may record cash.

main-panel separated the seat (booking) machine from the money (order) machine, and that separation was worth keeping. Its holds are expiry columns rather than states, and this ADR adopts the same idea.

## Decision

Each dimension is a **separate column with its own state machine**. They are defined in `workflow.yaml` (`type: state_machine`, `marking_store: {type: method, property: state}`):

| Workflow | Subject | States |
|---|---|---|
| `event_publication` | `Event.publicationState` | `draft → published → (cancelled \| completed → archived)` |
| `registration_window` | `Event.registrationState`, `Activity.registrationState` (two workflows sharing one definition through a supports list) | `not_open → open ⇄ paused → closed` |
| `participation` | `Participation.state` | `pending`, `confirmed`, `expired`, `cancelled`, `requires_attention`, `transferred` |
| `delivery` | `Delivery.state` | `scheduled → sent → delivered \| failed → retried`; `skipped` |
| `refund_task` | `RefundTask.state` | `open → settled` |
| `payment_request` | `PaymentRequest.status` (mirror of Pairrot) | `requested → issued → open → partially_paid \| paid \| overpaid`; `cancelled`; `failed` |
| `attention_item` | `AttentionItem.state` | `open → assigned → resolved \| dismissed` |
| `waitlist_offer` (P1) | `WaitlistOffer.state` | `offered → accepted \| declined \| expired` |

**Check-in and legal confirmations are not workflows.** They are append-only tables whose current state is the latest valid row (`check_in` with `voided_at IS NULL`, view `confirmation_current`). A correction or a withdrawal is a new row, never a transition of a column.

**Participation has further independent dimensions:** `legal_state` (`complete` / `reconfirmation_required`) and `answers_state` (`complete` / `completion_required`) are derived columns maintained by Legal and form-change handlers (S15, S17). They do not touch `state`; the UI combines them into a summary label „vyžaduje řešení“.

**Financial state is not a workflow.** It is a pure fold of the ledger (`FinancialState::fromEntries`) and is never transitioned directly ([ADR 001](001-modular-monolith-with-event-sourced-ledger.md)).

**Holds are not states.** A `pending` participation paid by bank transfer has `holdExpiresAt`, and a DB `CHECK` couples the two.

**Guards** are not expression strings. They are `#[AsGuardListener]` listeners that delegate to domain policies (`CapacityRestorePolicy`, `CancellationPolicy`) and voters (`is_granted('PARTICIPATION_CANCEL', subject)`), so domain rules stay testable without the Workflow component.

**Side effects through workflow events, never inside entities:**
- `WorkflowAuditSubscriber` listens to `workflow.*.completed`. It writes an `AuditEntry` with the actor from `TenantContext` and `ActorContext` (`USER`, `SYSTEM` or `PARTICIPANT_TOKEN`), the reason from the transition context (required for manual transitions through `#[RequiresReason]` metadata), and before/after (AUD-01–03).
- `WorkflowDomainEventSubscriber` maps transitions to domain events through workflow metadata `domain_event: ParticipationConfirmed`. The module publishes the integration event in the same transaction ([ADR 003](003-transactional-outbox-per-subscriber-delivery.md)).
- Capacity counter moves (`held → confirmed`, release) run in `workflow.participation.transition.*` listeners inside the Registration module, in the same transaction.

**Entities guard their own invariants as well.** `Participation::confirm()` is the only mutator. It asks the `participation` workflow to apply the transition, so the transition table is the single source of truth. Direct writes to `state` are banned by a PHPStan rule.

**Documentation.** `bin/console workflow:dump` output is rendered to the book diagrams in CI, so the documented state graphs cannot drift from the configuration.

## Consequences

**Positive**
- Independent dimensions are impossible to merge accidentally.
- Allowed transitions, guards, audit and events are declared in one place.
- The graphs are exportable.

**Negative**
- Workflow transitions are string-named. They are mitigated with enums for state and transition names and a test that the enum and the YAML match.
- Slight indirection: entity method → workflow → listeners.

## Alternatives considered

- **Hand-written state machines in entities (enum + `match`).** No declarative graph, so audit and events would be re-implemented per entity.
- **One `status` field per aggregate.** Explicitly forbidden by assignment chapter 5.
- **The `workflow` type (Petri net) instead of `state_machine`.** Multiple simultaneous places are not needed, and the separate dimensions already express parallelism.

## Verification

- `WorkflowDefinitionsTest` asserts, for every workflow, the allowed transitions from every state, matching the tables in [05](../architecture/05-stavove-automaty/README.md).
- An audit test asserts that a manual transition without a reason is rejected and that an automatic one is audited as `SYSTEM`.
- Scenario tests `S05`, `S06`, `S15` and `S16` exercise the guard and event paths.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 003](003-transactional-outbox-per-subscriber-delivery.md) · [05 State machines](../architecture/05-stavove-automaty/README.md), [09 Symfony](../architecture/09-symfony-v-architekture/README.md) · Supersedes: None.
