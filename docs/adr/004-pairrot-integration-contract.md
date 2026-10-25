---
status: accepted
last-verified: 2026-10-24
---

# ADR 004 — Pairrot integration: request by external_id, delta ledger from webhooks, reconcile from the event log

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/Payments/Infrastructure/Pairrot/` (`HttpPairrotGateway`, `PairrotWebhookAuthenticator`, `ApplyPairrotPaymentUpdateHandler`, `PairrotReconcileTask`) and the contract tests against `pairrot-sandbox`.

## Context

Pairrot is an existing external service. It matches incoming bank transfers to payment requests by variable symbol. Eventor must not duplicate the matching (assignment, chapters 1 and 10). The real contract (`chytre-digital/pairrot`, README and `docs/webhooks-receiver.md`) differs from the assumptions in the assignment (chapter 7.7):

- **API.** Bearer API keys and strict snake_case JSON. Amounts are integer haléře and the currency is CZK only.
- **Tenancy.** Root tenant → subtenants. A management key acts on `/v1/subtenants/{id}/…`. One webhook endpoint per root, with `scope: own_and_direct_children`.
- **Idempotent creation.** `POST …/payment-requests` is idempotent by `external_id`:
  - `201` on first create,
  - `200 replayed: true` for an identical repeat,
  - `409 external_id_conflict` for a different body.

  An `external_id` can never be reused, even after cancellation. Only `description` and `due_at` can be patched; **the amount is immutable**. Pairrot assigns the VS (`YYYYNNNNNN`) when omitted and returns a frozen SPAYD payload.
- **Webhooks report request state, not payments.** `payment_request.updated` carries `previous` / `current` `{status, paid_amount, remaining_amount, overpaid_amount}`, a per-request `sequence` and a `cause` (`allocation_created`, `allocation_revoked`, `transaction_review_required`, `request_cancelled`).
  - Signature: `Pairrot-Webhook-Signature: v1,<key-id>,<base64>`, HMAC-SHA256 over `"<id>.<timestamp>." + body` with a ±5 min window. Two signatures are present during a secret rotation.
  - Delivery is at least once, unordered, with gaps in `sequence`.
  - Retries last only about 1 h (5 s, 60 s, 1 h).
- **Auto-matching is exact-amount only.** Partial payments and overpayments arise only through **manual allocation** of a transaction (`POST …/transactions/{id}/allocations`). Unmatched transactions surface as `transaction.recorded` / `transaction.updated` with `matching.result` and appear in `/v1/management/attention`.
- **`due_at` only flags `is_overdue`.** Nothing expires in Pairrot.
- **No API to simulate an incoming bank payment.** The author provides a separate `pairrot-sandbox` service that implements the same API and emits signed webhooks.

## Decision

**Tenancy mapping.**
- Eventor has its own Pairrot root with a management key, stored in Symfony Secrets as `PAIRROT_MANAGEMENT_KEY`.
- Each `Organizer` is a subtenant `eventor_org_<uuid>`, created when the owner completes payment settings (ORG-02), along with a bank connection for the organizer's account.
- One webhook endpoint `/api/webhooks/pairrot` with `scope: own_and_direct_children` subscribes to `payment_request.updated`, `transaction.recorded` and `transaction.updated`.

**Creating requests (PAY-02, PAY-03, S08).**
- The outbox subscriber `CreatePaymentRequestOnOrderPlaced` calls `PaymentGateway::createRequest()` with:
  - `external_id = order_<orderUuid>_<attempt>`,
  - the amount due,
  - `due_at` set to the hold expiry,
  - no VS, so Pairrot assigns it.
- `201` and `200 replayed` are both success. `PaymentRequest` stores the Pairrot id, VS, IBAN, SPAYD payload and `last_sequence = 0`.
- Transport failures retry through the outbox backoff. Because `external_id` is idempotent, retries cannot create duplicates.
- Until the request exists, self-service shows "payment instructions are on the way" and emits `PaymentRequestIssued` once they are ready.

**Port and decorators.** `PaymentGateway` is the port. `HttpPairrotGateway` is the adapter, decorated in this order: `RetryingPaymentGateway` (only idempotent calls), `CircuitBreakerPaymentGateway`, then `PiiRedactingLoggingGateway`. The base URL comes from env `PAIRROT_API_URL`, which points to `pairrot-sandbox` locally and to the real Pairrot in production. No simulator lives inside Eventor. An in-memory `FakePaymentGateway` exists only in the `test` environment.

**Amount changes (REG-14, PAY-10, S18).** A request's amount cannot change, so `ReissuePaymentRequestOnAmountDueChanged` applies this rule:
- **No allocations yet** (`paid_amount = 0`): cancel the open request (`POST …/cancel` with `If-Match`) and create a new one with `attempt + 1`, sending updated instructions.
- **Allocations exist:** keep the original request. When the new amount due exceeds the amount paid, create a **supplementary request** `order_<uuid>_<attempt+1>` for the difference. When the amount due drops below the amount paid, the ledger shows an overpayment and a `RefundTask` follows.
- An order has at most one *open* request at a time.

**Applying webhooks (PAY-04, PAY-05, S03, S04, S07).**
1. `PairrotWebhookAuthenticator`, on a stateless firewall, verifies the signature against all configured secrets (`PAIRROT_WEBHOOK_SECRETS`, comma-separated, for rotation) with a constant-time compare and the timestamp window.
2. The controller inserts into `inbox_message(provider='pairrot', external_event_id=<event id>)` with `ON CONFLICT DO NOTHING` and returns `200`. Duplicates end here (S03). `webhook.test` events are acknowledged and ignored.
3. The inbox subscriber `ApplyPairrotPaymentUpdate`, inside one transaction:
   - locks `payment_request` and ignores the event unless `data.sequence > last_sequence`,
   - computes `delta = current.paid_amount − payment_request.known_paid_amount`,
   - appends a `LedgerEntry` of kind `PAIRROT_ALLOCATION` (positive delta) or `PAIRROT_REVOCATION` (negative delta) with `external_ref = <event id>` and a unique `(source, external_ref)`,
   - updates `known_paid_amount` and `last_sequence`, recomputes the `FinancialState` fold, and emits `OrderPaymentChanged`.

   Whether the payment is partial, full or an overpayment follows from the fold, not from the Pairrot status.

**Reconciliation.**
- The Scheduler task `PairrotReconcileTask` runs every 15 minutes. It reads `GET /v1/management/webhook-events?from=now-48h`, pages through, and feeds every event into the same inbox path, where it is deduplicated.
- Open requests whose `payment_change_sequence` is ahead of `last_sequence` are re-fetched.

**Unmatched transactions (PAY-06).**
- `transaction.recorded` / `transaction.updated` events with an unmatched `matching.result` are mirrored into `unmatched_transaction`, along with Pairrot's suggested candidates, and raise an `AttentionItem`.
- Finance staff allocate a transaction to an order from Eventor. Eventor calls `POST …/transactions/{id}/allocations` with an `Idempotency-Key`, and the result arrives back as a normal `payment_request.updated`. Eventor never writes a Pairrot allocation into its ledger directly.
- The action is audited with a mandatory reason.

**Expiry stays in Eventor.** Hold expiry is Eventor's own Scheduler sweep ([ADR 002](002-capacity-buckets-conditional-update.md)). A late payment after expiry raises an exception, or auto-restores the order when configured (CAP-05, S05, S06). On expiry Eventor deliberately does **not** cancel the Pairrot request, so a late transfer still auto-matches and reaches Eventor as a normal `payment_request.updated`. When the request is issued late (Pairrot outage, S08), `PaymentRequestIssued` extends `holdExpiresAt` so the participant keeps at least 48 hours to pay.

**Subscribed event types.** The root webhook endpoint subscribes to `payment_request.updated`, `transaction.recorded`, `transaction.updated`, `review_case.opened`, `review_case.updated`, `inbound_address.paused` and `inbound_address.resumed`. Only `payment_request.updated` can change money. The rest feed the reconciliation queue and the attention queue (`unmatched_payment`, `pairrot_review`, `integration_degraded`).

## Consequences

**Positive**
- Retries are safe by construction.
- The ledger is the single truth for money inside Eventor, while Pairrot stays the truth for bank evidence.
- Lost webhooks are recovered within 15 minutes.
- Unmatched payments get a real workflow.

**Negative**
- One more mirror table, plus delta bookkeeping (`known_paid_amount`).
- Supplementary requests mean a participant may see two payment instructions on one order. The self-service page shows only the currently open one.
- Only Česká spořitelna and CZK are supported by Pairrot today. This is accepted for the demo and documented in [11](../architecture/11-rizika-a-kompromisy/README.md).

**Security**
- Webhook secrets and the management key live in Symfony Secrets.
- Per-organizer Pairrot data in the database (subtenant id, bank connection id) is not secret.
- Logs never contain payer data, because Pairrot does not send it.

## Alternatives considered

- **Mapping Pairrot status directly to the order state.** It loses the history of partial allocations and revocations, and breaks "financial state is derived from immutable events".
- **Polling every open request.** Webhooks plus a 15-minute reconcile are cheaper and faster.
- **Eventor-assigned VS.** Possible (main-panel does it), but Pairrot's per-account sequence avoids collisions across organizers sharing an account.
- **A built-in simulator.** Rejected by the author in favour of `pairrot-sandbox`. Eventor then talks to exactly one API shape everywhere.

## Verification

- Contract tests (`tests/Contract/Pairrot/*`) against `pairrot-sandbox`: create and replay, the `409` conflict, cancel with `If-Match`, and a signed webhook round-trip.
- Scenario tests:
  - `S03` (the same event delivered three times gives one ledger entry and one email),
  - `S04` (partial payment gives `PARTIALLY_PAID` and a reminder),
  - `S05`/`S06` (late payment),
  - `S07` (overpayment gives an attention item and a refund task),
  - `S08` (Pairrot outage).
- Unit tests for signature verification, using the test vector from Pairrot's receiver document, a rotation with two signatures, and a skewed timestamp.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 003](003-transactional-outbox-per-subscriber-delivery.md), [ADR 006](006-authentication-session-tokens-no-jwt.md) · [07 Integrations](../architecture/07-integrace/README.md), [04 Key paths](../architecture/04-klicove-cesty/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

Decorator priorities are retry 30 (innermost), breaker 20 and logging 10. Webhook secrets are a list with optional `keyid:` prefixes; non-production subtenants use `test_`. Reconciliation performs its HTTP call inside the command transaction and has no daily limit. Ledger idempotency is `UNIQUE (organizer_id, source, source_ref)`.
