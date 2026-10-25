# Architecture Decision Records

The ADRs are written in English. The architecture book (`docs/architecture/`, Czech) is the map that sits above them. Template: [000-template.md](000-template.md).

| # | Title | Status | Date |
|---|---|---|---|
| [001](001-modular-monolith-with-event-sourced-ledger.md) | Modular monolith with stateful aggregates and an event-sourced payment ledger | accepted | 2026-10-24 |
| [002](002-capacity-buckets-conditional-update.md) | Capacity as buckets claimed by conditional UPDATE in the order transaction | accepted | 2026-10-24 |
| [003](003-transactional-outbox-per-subscriber-delivery.md) | Transactional outbox on the Messenger Doctrine transport, one transport per subscribing module | accepted | 2026-10-25 |
| [004](004-pairrot-integration-contract.md) | Pairrot integration: request by external_id, delta ledger from webhooks, reconcile from the event log | accepted | 2026-10-24 |
| [005](005-tenant-from-url-doctrine-filter-voters.md) | Tenant from the URL, Doctrine SQL filter, authorization by voters | accepted | 2026-10-24 |
| [006](006-authentication-session-tokens-no-jwt.md) | Authentication: session cookie for the team, purpose-bound tokens for participants and devices, no JWT | accepted | 2026-10-24 |
| [007](007-dynamic-form-schema-jsonb-snapshots.md) | Dynamic registration form: immutable JSONB schema snapshots, typed answers, server-side validation from one source | accepted | 2026-10-24 |
| [008](008-command-oriented-http-api.md) | Command-oriented HTTP API with thin controllers and value resolvers (no API Platform) | accepted | 2026-10-24 |
| [009](009-postgresql.md) | PostgreSQL 17 as the only datastore | accepted | 2026-10-24 |
| [010](010-monorepo-nextjs-frontend.md) | Monorepo with an API-first Symfony backend and a Next.js frontend on the same site | accepted | 2026-10-24 |
| [011](011-workflow-component-state-machines.md) | One Symfony Workflow state machine per state dimension | accepted | 2026-10-24 |
| [012](012-legal-documents-content-addressed-versions.md) | Legal documents as immutable, content-addressed versions; confirmations bound to a version | accepted | 2026-10-24 |
| [013](013-postgres-schema-per-module.md) | One PostgreSQL schema per module | accepted | 2026-10-25 |
