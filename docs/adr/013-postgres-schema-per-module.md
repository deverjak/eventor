---
status: accepted
last-verified: 2026-10-25
---

# ADR 013 — One PostgreSQL schema per module

**Status:** accepted 2026-10-25 · design only, no code yet. Proof will be the first migration (`CREATE SCHEMA` per module, grants), `#[ORM\Table(schema: …)]` on every entity, and the tests `TableOwnershipTest` and `CrossSchemaSqlTest`.

## Context

Eventor is a modular monolith on one database ([ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 009](009-postgresql.md)). Every table has exactly one owning module, and only that module writes to it. The first design put all tables into `public` and kept ownership in a manifest (`data-ownership.yaml`) checked by a test. Ownership was therefore invisible in the database itself, and a module's DBAL query could join another module's tables without anything noticing.

## Decision

**Each module owns one schema with the module's name**: `identity`, `catalog`, `legal`, `registration`, `payments`, `checkin`, `communication`, `content`, `privacy`, `audit`, `attention`, `reporting`. Technical tables without a domain owner live in `shared`: `messenger_messages`, `scheduled_run`, `idempotency_record`, `doctrine_migration_versions`. `public` stays empty.

- **Mapping.** Every entity names its schema: `#[ORM\Table(name: 'ledger_entry', schema: 'payments')]`. `TableOwnershipTest` asserts that every entity in `Eventor\<Module>\` maps to the schema `<module>`. The schema is the owner, so the ownership manifest keeps only retention classes.
- **Raw SQL is always qualified.** The application roles have `search_path = shared`, so an unqualified module table does not resolve, and every DBAL query names its schema (`FROM registration.participation`). `CrossSchemaSqlTest` scans the SQL in `src/<Module>/` and fails on any other module's schema. The only exception is the `reporting` schema, whose views are written in migrations.
- **Foreign keys cross schemas** where they did before (`registration.participation.activity_id → catalog.activity`), still with `ON DELETE RESTRICT`. Referential integrity is worth more than a pure separation that one database does not need.
- **Reporting** owns only views (`reporting.event_summary`, …), created by `eventor_owner`. `eventor_app` gets `SELECT` on them and nothing else in `reporting`.
- **Grants per schema.** Migrations grant `USAGE` and DML per schema and set `ALTER DEFAULT PRIVILEGES IN SCHEMA …` so new tables inherit them. The append-only revokes (`payments.ledger_entry`, `audit.audit_entry`, `legal.confirmation`) stay as they are.
- **Messenger** uses `table_name: messenger_messages`, resolved through `search_path` to `shared`. The `LISTEN/NOTIFY` trigger is created by the migration (`auto_setup: false`).

## Consequences

**Positive**
- Ownership is visible in the database (`\dn`, `\dt payments.*`) and in every SQL string.
- A cross-module SQL join becomes a test failure, not a code-review catch.
- Extracting a module starts with `pg_dump --schema=payments`.

**Negative**
- This is not a security boundary. One application role can still read every schema. Real isolation would need a role and a connection per module, which is too much for a monolith.
- Doctrine Migrations on PostgreSQL may generate a spurious `CREATE SCHEMA public` in `down()` when the default schema is not managed. A `postGenerateSchema` listener adds `public` to the schema model, and generated migrations are reviewed anyway.
- Grants and future RLS policies are written per schema, which adds a few lines to each migration.

## Alternatives considered

- **Everything in `public` with an ownership manifest** (the first design). Works, but ownership exists only in YAML and cross-module SQL goes unnoticed.
- **Table prefixes** (`pay_ledger_entry`). Same visibility with uglier names, and no per-schema grants or dumps.
- **A database per module.** True isolation, but it breaks the single transaction that registration needs ([ADR 002](002-capacity-buckets-conditional-update.md)) and cross-module FKs.

## Verification

- `TableOwnershipTest`: entity namespace ↔ schema.
- `CrossSchemaSqlTest`: no foreign schema in a module's SQL, except views in `reporting`.
- `SchemaGrantsTest` connects as `eventor_app` and checks `USAGE` on every module schema, `SELECT` only on `reporting`, and the append-only revokes.
- `doctrine:schema:validate` in CI passes without diff noise.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 003](003-transactional-outbox-per-subscriber-delivery.md), [ADR 005](005-tenant-from-url-doctrine-filter-voters.md), [ADR 009](009-postgresql.md) · [03 Data](../architecture/03-data/README.md), [02 Modules](../architecture/02-moduly-a-hranice/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

The PostgreSQL `search_path` is set in the initialization script. Messenger does not currently use the `LISTEN/NOTIFY` wake-up described in this ADR.
