# AGENTS.md

Symfony 8.1 / PHP 8.4 modular monolith. **Start with [`README.md`](README.md)** (exact commands, wiring of a module, test setup) and [`../docs/implementation/README.md`](../docs/implementation/README.md) (build goal and binding rules for agents).

Hard rules in short:

* PHP is not installed locally. Run everything through Docker (`docker compose exec php …`, `bin/check`, `bin/test-db <token>`, `bin/api-test`). Do not commit.
* Change only your module (`src/<Module>/`, `tests/*/<Module>/`) and files you were assigned. Other modules only through their `Contract/`. Shared files (`config/packages/*`, `composer.json`, `src/Shared`, `config/services_test.php`, `deptrac.yaml`) belong to the coordinator: put a request in your report.
* Module configuration (services, Messenger transport, workflows, schedules, Doctrine types) goes into `src/<Module>/Infrastructure/Symfony/services.php` via `ModuleServices` / `$container->extension('framework', …)`.
* Config is PHP (`config/packages/*.php`, `App::config([...])`), no YAML. Attributes for routing, validation, handlers, listeners, aliases.
* Writes are commands on `command.bus` (one transaction, audit, outbox); reads are query services (DBAL) returning typed `*View` DTOs, never `array<string, mixed>`. Naming (book ch. 09 "Čtení: query služby"): Contract facade by role (`EventCatalog`, `OrganizerLookup`), its implementation `<Facade>Query` in `Application/Query/`, a screen/endpoint read model `<Area>Query` (`AdminEventQuery::detail()` → `EventDetailView`), views in `Application/Query/View/` (or `Contract/` when part of a facade), presenters in `Application/Presenter/`. Only query services and views live in `Query/`. Every command records a domain event. Tenant from the URL (`#[CurrentOrganizer]`), permission with `#[RequiresPermission]`.
* Migrations are hand-written, tables only in the module's schema, raw SQL always schema-qualified.
* Tests with `TEST_TOKEN=<module>` (database `eventor_test_<module>`): unit, integration, API black-box. Definition of done: README section 4.
* No `*Manager` / `*Service` god classes; one use case = one handler; no floats for money; no `new \DateTime()` (inject `ClockInterface`).
