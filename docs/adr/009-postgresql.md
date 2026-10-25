---
status: accepted
last-verified: 2026-10-24
---

# ADR 009 — PostgreSQL 17 as the only datastore

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be the `compose.yaml` service `postgres:17` and the Doctrine migrations that use partial indexes, `CHECK` constraints, JSONB with GIN, and `REVOKE` grants.

## Context

MySQL and MariaDB are historically the most common pairing with PHP. The architecture, however, relies on several database features as the **last line of defence for invariants**:

- **Exactly one valid check-in per participation** under concurrency (CHK-06, S10). This needs a **partial unique index** (`… WHERE valid`).
- **Capacity limits as `CHECK` constraints** on `capacity_bucket` ([ADR 002](002-capacity-buckets-conditional-update.md)).
- **Dynamic form answers in JSON** that support filtering and indexing (ADM-04, ADM-05, [ADR 007](007-dynamic-form-schema-jsonb-snapshots.md)).
- **A transactional outbox and queue**: the Messenger Doctrine transport claims rows with `FOR UPDATE SKIP LOCKED` and wakes workers via `LISTEN/NOTIFY` ([ADR 003](003-transactional-outbox-per-subscriber-delivery.md)).
- **Append-only tables** enforced through grants and verified by tests ([ADR 001](001-modular-monolith-with-event-sourced-ledger.md)).
- **Optional row-level security** as a second tenancy line in P1 ([ADR 005](005-tenant-from-url-doctrine-filter-voters.md)).
- **Migrations that roll back completely** when they fail.

Pairrot and main-panel already run on PostgreSQL, so the operational knowledge exists.

## Decision

**Engine.** PostgreSQL 17 is the only datastore. It holds domain data, the outbox, the inbox, sessions (`PdoSessionHandler`), Messenger transports (`doctrine://default`), Scheduler state, locks and rate-limiter storage. In P0 there is no Redis, no RabbitMQ and no Elasticsearch.

**Features used deliberately:**
- partial unique indexes: `check_in (participation_id) WHERE voided_at IS NULL`, open `payment_request` per order, active `waitlist_offer`,
- `CHECK` constraints for counters, money that must not be negative where applicable, and state/expiry coupling (`state = 'PENDING' ⇔ hold_expires_at IS NOT NULL` for bank transfer),
- `jsonb` + GIN (`jsonb_path_ops`) for `answer_set.values` and `form_version.schema`,
- `FOR UPDATE SKIP LOCKED` in the Messenger Doctrine transport and the expiry sweeper,
- `LISTEN/NOTIFY` through Messenger's PostgreSQL transport option `use_notify: true`,
- transactional DDL, so every Doctrine migration runs in a transaction,
- a role split: migrations run as `eventor_owner`, the application runs as `eventor_app`, which lacks `UPDATE`, `DELETE` and `TRUNCATE` on `ledger_entry`, `audit_entry` and `confirmation`; on `check_in` it holds only a column-level `GRANT UPDATE (voided_at, voided_by, void_reason)`, so the one permitted change is voiding a record (a trigger rejects un-voiding),
- `timestamptz` everywhere, with the session time zone set to UTC.

**Doctrine.** `doctrine/dbal` 4 runs with the PostgreSQL platform. Custom DBAL types are `money_minor` (int), `uuid` (via `symfony/uid`) and an encrypted `secret` type for organizer-level credentials. Migrations are hand-reviewed, because partial indexes and grants live in raw SQL inside the migration.

## Consequences

**Positive**
- Invariants are enforced at the database, not only in PHP.
- One piece of infrastructure to run, back up and test.
- Integration tests run on the exact production engine (Docker `postgres:17`).

**Negative**
- Some SQL is PostgreSQL-specific (partial indexes, `SKIP LOCKED` semantics, JSONB operators). This is accepted; portability is not a requirement.
- Sessions and rate limits in Postgres are slower than in Redis. That is fine at demo scale, and swapping them is a config change (Redis DSN) if needed.

## Alternatives considered

- **MySQL 8 / MariaDB.**
  - No partial indexes. Workarounds with generated columns plus a unique index are clumsy and easy to break.
  - JSON indexing works only through generated columns.
  - DDL is not transactional.
  - No RLS.
  - `SKIP LOCKED` exists in MySQL 8, but it alone does not outweigh the rest.
- **PostgreSQL + Redis from day one.** Redis would add a second stateful service without a requirement that Postgres cannot meet. It is kept as an optional transport and cache later.

## Verification

- `S10_ConcurrentCheckInTest` relies on the partial unique index: two parallel check-ins give one row and one `409` carrying who and when.
- `AppendOnlyGrantsTest` connects as `eventor_app` and asserts that `UPDATE` and `DELETE` on the append-only tables fail.
- `MigrationsRoundTripTest` runs the migrations up and down on an empty database in CI.

## Related

[ADR 002](002-capacity-buckets-conditional-update.md), [ADR 003](003-transactional-outbox-per-subscriber-delivery.md), [ADR 007](007-dynamic-form-schema-jsonb-snapshots.md) · [03 Data](../architecture/03-data/README.md), [01 Context and deployment](../architecture/01-kontext-a-nasazeni/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

`search_path` is set by the initialization script. The idempotency store uses `shared.idempotency_record` and stores completed responses as JSON. No `use_notify` / `LISTEN/NOTIFY` outbox wake-up is configured.
