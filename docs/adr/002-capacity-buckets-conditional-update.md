---
status: accepted
last-verified: 2026-10-24
---

# ADR 002 — Capacity as buckets claimed by conditional UPDATE in the order transaction

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/Registration/Infrastructure/Capacity/DbalCapacityAllocator.php`, the `capacity_bucket` migration with its `CHECK` constraint, and the scenario tests `S01_LastSeatRaceTest` and `S02_AllOrNothingOrderTest`.

## Context

- **Capacity levels (CAP-01).** Capacity exists at event, activity, variant and, from P1, wave level. A participation occupies **every** relevant level at once. A team variant may occupy N seats or one team seat (CAP-07, P1).
- **Atomic claim (CAP-02).** Claiming all levels for all participations of an order must be atomic, and concurrent registrations must never exceed a limit.
  - S01: two people race for the last seat, and exactly one wins.
  - S02: an order that touches a full activity and a free one is created entirely or not at all.
- **Three counts (CAP-03).** Available, held and confirmed capacity are distinguished in both the public and the admin view.
- **Holds expire (CAP-04).** A pending bank-transfer participation holds its seat until a TTL expires. The ledger, not a timer, decides when it is paid.
- **Lowering a limit (CAP-06).** An organizer may lower a limit below current occupancy. That produces a warning and an audit entry, never automatic cancellations.
- **Load (NFR).** The system must cope with hundreds of concurrent users when registrations open.

## Decision

Capacity is stored as **one row per capacity level** in `capacity_bucket`:

```sql
capacity_bucket(id uuid pk, organizer_id, scope_type text /* EVENT|ACTIVITY|VARIANT|WAVE */,
                scope_id uuid, "limit" int null /* null = unlimited */,
                held int not null default 0, confirmed int not null default 0,
                CHECK (held >= 0 AND confirmed >= 0))
participation_capacity_claim(participation_id, bucket_id, seats int, kind text /* HELD|CONFIRMED */)
```

**Claiming.** `PlaceOrderHandler`, inside its single transaction, calls `CapacityAllocator::claim(ClaimRequest[])`. The allocator:

1. aggregates the seats needed per bucket across all participations of the order,
2. sorts the buckets by `id`, a deterministic lock order that prevents deadlocks,
3. runs one statement per bucket:

   ```sql
   UPDATE capacity_bucket SET held = held + :n
    WHERE id = :id AND ("limit" IS NULL OR held + confirmed + :n <= "limit")
   ```

4. on `0 rows affected`, throws `CapacityExhausted(bucketId, scope)`. The whole transaction rolls back, so the order, persons, participations and every earlier bucket increment disappear together (S02). The API maps the error to `409 capacity_exhausted` and adds a waitlist offer when the waitlist is enabled (P1).

Cash and free participations are claimed directly into `confirmed`. Bank-transfer participations are claimed into `held` and the participation gets `holdExpiresAt`.

**Moving between counters.** Confirmation (`held → confirmed`), expiry and cancellation (release) are guarded single-row `UPDATE`s, executed in the same transaction as the Workflow transition of the participation. A variant change (S18) claims the new buckets and releases the old ones in one transaction.

**Lowering a limit (CAP-06).** Lowering a limit only updates `limit`. The guard runs on increments only, so existing occupancy is never revoked. The admin UI shows the overbooking as a warning.

**Last-resort guard.** The table carries `CHECK ("limit" IS NULL OR held + confirmed <= "limit" OR overbooked_allowed)`. `overbooked_allowed` is set only by the CAP-06 decrease path.

**Reads.** Availability (PUB-03) is read from the buckets, never by counting participations. `CachedAvailabilityReader` decorates the reader with a short TTL and tag invalidation when buckets change.

## Consequences

**Positive**
- Exactly one SQL round-trip per bucket.
- Row locks are held only from the `UPDATE` to `COMMIT`. Validation, pricing and legal resolution happen *before* the first `UPDATE`, which keeps the critical section short.
- S01 and S02 hold by construction.

**Negative**
- The counters are denormalised and can drift if code bypasses the allocator. Mitigations:
  - only `DbalCapacityAllocator` writes these columns (a PHPStan rule),
  - a reconciliation query (`CapacityConsistencyCheck`) compares the buckets with the sums of claims, both in tests and nightly through Scheduler, and drift becomes an `AttentionItem`.
- Hot rows: the event-level bucket is touched by every order. With short transactions this is acceptable for hundreds of concurrent users, and the load test in [10](../architecture/10-kvalita-a-testovani/README.md) measures it.

## Alternatives considered

- **`SELECT … FOR UPDATE` on all buckets, then check and write.** Correct, but two round-trips per bucket, and it invites holding the locks over validation code.
- **Optimistic `version` column with retries.** A registration-rush stampede produces retry storms on the hot event bucket.
- **Counting participations under an advisory lock per activity.** It serialises a whole activity, and the count gets slower as the table grows.
- **An event-sourced capacity aggregate.** Rejected in [ADR 001](001-modular-monolith-with-event-sourced-ledger.md).

## Verification

- `S01_LastSeatRaceTest` forks N PHP processes, which place orders at once against a bucket with one seat left. It asserts that exactly one returns `201`, the rest return `409`, and `held + confirmed = limit`.
- `S02_AllOrNothingOrderTest` asserts that after the conflict no participation exists and no bucket changed.
- `CapacityConsistencyCheck` runs after every integration test that touches registration.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 011](011-workflow-component-state-machines.md) · [03 Data](../architecture/03-data/README.md), [04 Key paths](../architecture/04-klicove-cesty/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

`capacity_bucket` also carries `event_id`; scope, unit and claim status are lowercase. Buckets are provisioned for every scope, including unlimited ones. `CapacityReleaser` publishes releases directly; a limit raised to unlimited publishes `seats = 1`. The production availability cache is Postgres-backed with a 30-second TTL and tags; `CapacityInvariantViolated` is an internal plain exception.
