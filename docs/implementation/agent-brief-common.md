# Common brief for module agents (W2+)

You are a module engineer on Eventor, a Symfony 8.1 modular monolith (repo `C:\Users\kubad\Documents\coding\eventor`). Several agents work **in parallel in the same working tree**, each on its own module. A coordinator reviews and commits. **Never commit, never `git stash/checkout/reset`, never run `composer require`.**

## Read first
1. `docs/implementation/README.md`: the goal and the binding agent rules.
2. `backend/README.md` and `backend/AGENTS.md`: exact commands, conventions, how to add a transport, workflow or migration.
3. `docs/implementation/contracts.md`: the public Contract of every module. Implement your module's Contract exactly. Consume other modules only through theirs.
4. The book chapters for your module (`docs/architecture/`, Czech) and the spec features (`docs/initial-idea/zadani-event-platforma.md`, feature IDs).
5. The existing code in `backend/src/Shared` and `backend/src/Identity`. Copy the idioms you find there: use-case folders, `final readonly`, handlers on `command.bus`, DBAL query services that return DTOs, `#[AsProblem]` exceptions, `ModuleServices` helpers and the test base classes.

## Isolation in a shared tree
- **Test DB.** Run everything with `TEST_TOKEN=<your module, lowercase>`. Re-run `bin/test-db <token>` after you add a migration. Other agents add migrations at the same time, so if a foreign migration breaks, wait and retry. Never edit it.
- **Files you may edit:**
  - `backend/src/<YourModule>/**`
  - `backend/tests/{Unit,Integration,Api}/<YourModule>/**`
  - your own migrations, whose description starts with the module name
  - any extra files your task lists explicitly
- **Shared files** (`config/packages/*`, `config/services*.php`, `security.php`, `translations/*`, `bin/*`, `deptrac.yaml`, `src/Shared`) are off-limits unless your task grants them. Prefer module-local alternatives:
  - test-only service aliases go in your `services.php`, guarded by `if ('test' === $container->env())`;
  - problem translations go in a per-module translation path registered from your `services.php`, if the translator supports it; otherwise ask in your report;
  - module config goes through `$container->extension('framework', …)` in your `services.php`.
- **If your task does grant a shared file:** re-read it right before each edit, change only your block, and keep the edit small. Another agent may edit the same file between your reads.
- **Pending subscribers.** When you write a subscriber of an integration event, remove your module from that event's `#[PendingSubscribers]` in the same change. This edit to a foreign `Contract/Event` file is allowed.
- **Contract changes.** If a foreign Contract lacks something you need, do not change it. Work around it with what exists if that is reasonable, and put the request in your report. Small additive changes to **your own** Contract are fine; list them in the report.

## Tests: every part has its tests
- **Unit** (`tests/Unit/<Module>`): domain rules without a kernel.
- **Integration** (`tests/Integration/<Module>`):
  - each command handler, including the emitted event, the DB effects and the outbox message;
  - each HTTP endpoint through `WebTestCase`: happy path, validation 422, 401, 403 for an insufficient role, 404 for a foreign organizer;
  - workflows and guards.
- **Black-box API** (`tests/Api/<Module>`): real HTTP calls against `php-api` through `ApiTestCase`. Every endpoint needs at least one happy-path test and one error test. Create test data through the API wherever possible, using unique slugs and emails per test. You can only use endpoints that already exist. If your test needs seed data from a console command, ask in the report rather than editing `bin/api-test`. Run the suite with `bin/api-test`; it restarts the worker-mode server.
- The architecture tests (`TenantIsolationTest`, `CommandEmitsEventTest`, `TableOwnershipTest`, `CrossSchemaSqlTest`, routing) must stay green. They cover your module automatically.
- Done means green on: `bin/check` with your `TEST_TOKEN` (cs, PHPStan max, deptrac, warmup, unit, integration, scenario) and `bin/api-test`. If something another agent is changing breaks them, say so precisely in your report. Do not "fix" their code.
- Every endpoint gets OpenAPI attributes (nelmio).

## Final report (concise, English)
- Feature IDs: done / partial / not done.
- Endpoints (method + path).
- Events emitted and consumed.
- Migrations.
- Test counts per suite, taken from the actual output.
- Deviations from the book or the contracts.
- Requests for the coordinator, such as shared-file changes or Contract additions.
