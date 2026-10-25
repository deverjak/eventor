# Eventor backend

Symfony 8.1 modular monolith on PHP 8.4 / FrankenPHP / PostgreSQL 17. Architecture: [`docs/architecture`](../docs/architecture/README.md) (Czech book) and [`docs/adr`](../docs/adr/). Build rules for agents: [`docs/implementation/README.md`](../docs/implementation/README.md).

**PHP is not installed on the host. Everything runs in Docker.** All commands below are run from the repository root (Git Bash / PowerShell / Linux shell alike). On Windows with Git Bash prefix raw `docker compose exec` commands that contain a leading slash with `MSYS_NO_PATHCONV=1`; the `bin/*` scripts do it for you.

## 1. The stack

```bash
docker compose up -d --build          # postgres, composer install, migrations, php, worker, php-api, worker-api, smtp4dev, minio, pairrot*
bin/pairrot-bootstrap                 # once: builds Pairrot, creates the "eventor-local" root tenant, writes keys to backend/.env.local
# `docker compose up` already ran the migrations (one-shot services `migrate`, `migrate-api`); to run them by hand:
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console eventor:identity:create-owner \
    --email=jana@example.test --password=correct-horse-battery --organizer-name="Klub Plzeň" --slug=klub-plzen
```

\* `pairrot` is built from `${PAIRROT_PATH:-../chytre-digital/pairrot}` and only starts correctly after `bin/pairrot-bootstrap` (its schema is created by the admin CLI).

| Service | Host port | Purpose |
|---|---|---|
| `php` | 8080 | dev API, FrankenPHP **classic** mode: every request boots a fresh kernel, code edits apply at once |
| `worker` | - | `messenger:consume --all --exclude-receivers=failed` (all `<module>_events` transports, `async`, scheduler) |
| `php-api` / `worker-api` | 8081 | same image, database `eventor_api`, FrankenPHP **worker** mode: target of the black-box API tests. Needs `docker compose restart php-api worker-api` after code edits (`bin/api-test` does it) |
| `postgres` | 5434 (`POSTGRES_HOST_PORT`) | roles `eventor_owner` (migrations), `eventor_app` (application), databases `eventor`, `eventor_api`, `pairrot`, `eventor_test_<token>` |
| `smtp4dev` | 5000 | mail catcher (UI + REST) |
| `minio` | 9000 / 9001 | S3 API / console (`eventor` / `eventor-secret`), buckets `eventor-media`, `eventor-media-test`. The official `minio/minio` images are no longer published, so the frozen `bitnamilegacy/minio` build is used |
| `pairrot` | 8090 | real Pairrot API (inside the network: `http://pairrot:8080`) |

* **Port clash** (e.g. 5434 taken): create the git-ignored `compose.override.yaml`, e.g. `services: { postgres: { ports: !override ["5435:5432"] } }`.
* **Fresh database roles** (`docker compose down -v && docker compose up -d`) re-run `docker/postgres/10-init.sh`.
* `vendor/` and `var/` live in **named Docker volumes** (fast on Windows). `backend/vendor` on the host is empty and irrelevant.
* Only the coordinator runs `composer require`: `docker compose run --rm --no-deps php composer require …`.

Everyday commands:

```bash
docker compose exec php bin/console <command>          # console
docker compose exec php bin/console debug:router | debug:container <x> | debug:messenger
docker compose logs -f worker                          # what the worker does
docker compose exec php vendor/bin/php-cs-fixer fix    # or: bin/check --fix
```

## 2. Tests and checks

| Suite | What | Run |
|---|---|---|
| `unit` | no kernel, no DB: domain, value objects, compiler passes on hand-made containers | `docker compose exec php vendor/bin/phpunit --testsuite unit` |
| `integration` | kernel + Postgres, **each test in a rolled-back transaction** (dama). HTTP via `WebTestCase`. Includes `tests/Integration/Architecture` | needs a test DB, see below |
| `scenario` | whole behaviour across modules, in-memory transports, `MockClock`, queues drained in-process | needs a test DB |
| `contract` | adapters against the real neighbours (Pairrot smoke test now) | `bin/pairrot-bootstrap` first |
| `api` | **black-box**: real HTTP against `php-api` (+ `worker-api`), no kernel, no DB | `bin/api-test` |

### Test database per agent: `TEST_TOKEN`

Parallel agents must not share a database or a compiled test container. Pick a token (`[a-z0-9]+`, your module name) and use it everywhere:

```bash
bin/test-db identity                                   # drops/creates + migrates eventor_test_identity
docker compose exec -e TEST_TOKEN=identity php vendor/bin/phpunit --testsuite integration
docker compose exec -e TEST_TOKEN=identity php vendor/bin/phpunit --testsuite scenario
docker compose exec -e TEST_TOKEN=identity php vendor/bin/phpunit --filter CurrentOrganizerTest
```

`TEST_TOKEN` selects the database (`doctrine.dbal.*.dbname_suffix: _test_%env(TEST_TOKEN)%`) and the compiled container (`var/cache/test_<token>`, see `Kernel::getCacheDir()`). Without it the token is `default`. **Re-run `bin/test-db <token>` after adding a migration.**

### `bin/check` - the gate before "done"

```bash
bin/check            # php-cs-fixer (dry run), PHPStan max, deptrac, container warm-up (prod + test), lint:container, unit, integration, scenario
bin/check --fix      # let php-cs-fixer rewrite files
bin/check --fast     # skip the database suites
```

Individually: `vendor/bin/phpstan analyse`, `vendor/bin/deptrac analyse`, `php bin/deptrac-layers` (regenerates `deptrac.yaml` when a module is added; `--check` verifies it), `vendor/bin/php-cs-fixer fix`.

### Black-box API suite

```bash
bin/api-test                       # resets eventor_api, migrates, seeds, restarts php-api/worker-api, runs phpunit --testsuite api
bin/api-test --filter OrganizerApiTest
```

Seed accounts (created by the console in `bin/api-test`, mirrored in `tests/Api/Support/ApiFixtures.php`): `owner.alpha@api-test.example` / `alpha-owner-password` (organizer `api-alpha`), `owner.beta@api-test.example` / `beta-owner-password` (organizer `api-beta`). Extend the SEED block of `bin/api-test` **and** `ApiFixtures` together when you need more bootstrap data; anything the API can create should be created through the API.
Tests extend `Eventor\Tests\Api\Support\ApiTestCase`: `$this->anonymous()`, `$this->loginAs(ApiFixtures::ALPHA)` (cookie jar per client, `csrf-token` header added automatically), `$this->assertProblem($response, 404, 'not_found')`. Every public endpoint needs at least one happy-path and one error test (validation / 401 / 403 / foreign-tenant 404).

### Writing integration / scenario tests

* `Eventor\Tests\Support\IntegrationTestCase` (KernelTestCase): `commandBus()`, `createOwner($email, $slug)` (real command), `drainQueues()`, `dbInt/dbString/dbRow/dbRows/dbColumn()` typed SQL helpers, `service(Foo::class)` for services exposed by a public `test.<FQCN>` alias (add yours to `config/services_test.php`), `mockTime('2026-11-01 12:00')` / `ClockSensitiveTrait`.
* `Eventor\Tests\Support\HttpTestCase` (WebTestCase): `request($method, $uri, $json, $headers, csrf: true)`, `login($email)`, `assertProblem($status, $code)`, `forgetLoadedEntities()`.
* `Eventor\Tests\Scenario\ScenarioTestCase`: frozen `MockClock` at `START`, in-memory `<module>_events` transports; `drainQueues()` runs a real Messenger `Worker` (retry strategy, failure transport and `DeadLetterListener` included).
* Foundry factories in `tests/Support/Foundry` (`UserAccountFactory`, `OrganizerFactory`, `MembershipFactory`, named-constructor instantiation). Add yours per module there or under `tests/Support/Foundry/<Module>`.
* Test-only wiring lives in `config/services_test.php`; fixtures in `tests/Fixtures`.

## 3. How a module is wired (read before writing code)

```
src/<Module>/
  Contract/            public: facade interfaces, DTOs, Event/*, Exception/*, Attribute/*   (-> Shared only)
  Domain/              entities (#[ORM\Entity], schema = module), value objects, repositories (interfaces), Event/ (internal);
                       may use Shared and the module's own Contract (public exceptions, Contract enums)
  Application/         <UseCase>/ command + handler, Query/ (DBAL read models), Port/, EventPublisher/
  Infrastructure/      Doctrine repositories, adapters, Symfony/<Module>Module.php + services.php
  UserInterface/       Http/ (controllers, ValueResolver/), EventHandler/ (integration-event subscribers), Console/, Security/
```

deptrac enforces it (`deptrac.yaml` is generated): another module is reachable **only** through its `Contract`. `ModuleBoundaryPass` enforces the same in the DI container at `cache:warmup`.

### `src/<Module>/Infrastructure/Symfony/services.php` is the only place for module configuration

It is imported by the `Kernel` (glob `src/*/Infrastructure/Symfony/services.php`). It never touches `config/packages/*`.

```php
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Payments', \dirname(__DIR__, 2));   // autowired services + Doctrine mapping of Domain/
    ModuleServices::dbalTypes($container, [OrderIdType::NAME => OrderIdType::class]);     // typed-id / custom DBAL types
    ModuleServices::eventTransport($container, 'payments');                              // transport `payments_events` (outbox; in-memory in tests)

    $container->extension('framework', [                                                  // module-specific framework config, merged with the others
        'workflows' => ['payment_request' => [...]],
        'messenger' => ['transports' => ['pairrot_inbox' => ['dsn' => 'doctrine://default?queue_name=pairrot_inbox', 'options' => ['auto_setup' => false]]]],
    ]);
    $services->set(...)->decorate(...);                                                   // explicit services, decorators, aliases
};
```

* **Transport / workflow / scheduler**: as above. A scheduler is an `#[AsSchedule]` provider class in the module; the worker consumes `scheduler_*` automatically (`--all`).
* **Aliases of Contract interfaces**: `#[AsAlias(OrganizerDirectory::class)]` on the implementing class (the `Contract` folder is not scanned for services).
* **Module compiler pass / attribute autoconfiguration**: in `<Module>Module::build()`.
* **A new module**: add the class to `Kernel::modules()`, run `php bin/deptrac-layers`, add it to `tests/Integration/Architecture/ModuleCatalog::SCHEMAS`.

### Commands, events, subscribers

* Write = **command** through `Eventor\Shared\Application\Messaging\CommandBus` (`dispatch($command, ...$stamps): mixed`, exceptions come out unwrapped). Chain on `command.bus`: validation → tenant → idempotency → `doctrine_transaction` → audit context → domain events → handler. Handlers: `#[AsMessageHandler(bus: 'command.bus')]`, no `flush()`/transactions. Reads never use a bus: controllers call query services (DBAL → DTO).
* **Every command records a domain event** (aggregate: `use RecordsEvents` + `implements RecordsEventsInterface`, `$this->record(new XHappened(...))`), or the command class is `#[ReadOnlyCommand]`, or the handler is `#[NoDomainEvent('reason')]`. Enforced in dev/test by `DomainEventsMiddleware`.
* **Audit**: a domain event implementing `Auditable` is audited automatically in the same transaction (actor + correlation id from `AuditContext`). No personal data in `AuditChange` (Audit module's `audit_entry`).
* **Integration events**: class in `<Module>\Contract\Event`, `#[IntegrationEvent('module.snake_name', version)]`, ids and neutral values only. Published from a synchronous `#[AsMessageHandler(bus: 'event.bus')]` that translates the domain event (see `Identity\Application\EventPublisher\PublishMembershipChanged`). Subscribers: `#[AsMessageHandler(fromTransport: '<module>_events')]` in `UserInterface/EventHandler`, **one handler per event and module**, writing only through the module's own commands. Routing is derived from the subscribers by `IntegrationEventRoutingPass`; an event without subscribers needs `#[NoSubscribers]`. Delivery is at-least-once: subscribers must be idempotent. Retries follow `ScheduleRetryStrategy` (1 min, 5 min, 30 min, 2 h, 12 h, then `failed` + an Attention item).
* **Contract-first scaffold**: all integration events and facades already exist ([`docs/implementation/contracts.md`](../docs/implementation/contracts.md) lists them per module). Every event carries `#[PendingSubscribers('payments', ...)]` (lower-case module names). When you write a subscriber, **remove your module from that attribute in the same change**: `IntegrationEventRoutingPass` fails the build while a module with a real handler is still listed (and when an event has neither a handler, `#[NoSubscribers]` nor a pending list). `EVENTOR_REPORT_PENDING=1 … phpunit --filter PendingSubscribersTest` prints what is left. Typed ids that cross modules (`OrderId`, `ParticipationId`, ...) are in `Shared\Domain\Id`; their DBAL types are registered by the Shared module (`SharedIdTypes`), just map them with `#[ORM\Column(type: OrderIdType::NAME)]`. A foreign Contract may only be used if `config/module_contracts.php` lists the edge (then `php bin/deptrac-layers`).
* **Idempotency**: controller takes `IdempotencyKey $key` (mandatory) or `?IdempotencyKey` (optional) and dispatches with `IdempotencyStamp::for($scope, $key->value, $canonicalBody)`; command results must be JSON-serialisable.

### Controllers (admin zone shape)

```php
#[AsController]
final class CancelOrderController extends AbstractController
{
    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/cancel', methods: ['POST'])]
    #[RequiresPermission(Permission::RegistrationEdit)]            // mandatory under /api/admin (PermissionMatrixPass)
    #[OA\Post(summary: '…', tags: ['orders'], responses: [...])]  // OpenAPI via nelmio; GET /api/doc.json
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId, #[MapRequestPayload] CancelOrderRequest $body): JsonResponse
}
```

Order of failures: 401 (firewall) → 404 (unknown organizer or no membership) → 403 (voter). Tenant-scoped entities implement `TenantScoped` (field `organizerId`, column `organizer_id`); the Doctrine `organizer` filter is always on and throws `TenantNotSet` outside a tenant. DBAL queries take an explicit `OrganizerId`. Cross-tenant reads only via `TenantFilterGuard::crossTenant($reason, $fn)`.

Errors: throw a `DomainException` subclass with `#[AsProblem(code: 'capacity_exhausted', status: 409)]` (override `context()` / `nextSteps()`); it becomes an RFC 9457 problem. Add a title to `translations/problems.{cs,en}.php` (`problem.<code>.title`).

**CSRF** (cookie session): unsafe requests under `/api/admin` and `/api/auth` must carry a header `csrf-token: <random string of ≥24 chars>` (any value, a cross-site page cannot set it) and, if present, a same-origin `Origin`/`Referer`. Clients simply generate it per request (`crypto.randomUUID()`); there is no token endpoint. Public, self-service, webhook and device zones are not cookie-based and are exempt (configure prefixes in `CsrfGuardListener`).

### Database

* One schema per module (`identity`, `catalog`, `legal`, `registration`, `payments`, `checkin`, `communication`, `content`, `privacy`, `audit`, `attention`, `reporting`) + `shared`. Entities carry `#[ORM\Table(name: …, schema: '<module>')]`. **All raw SQL is schema-qualified** (the app role's `search_path` is `shared`), and a module's SQL names only its own schema (`CrossSchemaSqlTest`).
* Migrations are **hand-written**, `backend/migrations/VersionYYYYMMDDHHMMSS.php`, description starts with the module (`'Payments: ledger_entry, payment_request'`), tables only in the module's schema. They run as `eventor_owner`; default privileges already give `eventor_app` SELECT/INSERT/UPDATE/DELETE on new tables (reporting: SELECT only). Append-only table? `REVOKE UPDATE, DELETE, TRUNCATE ON <t> FROM eventor_app` **and** list it in `ModuleCatalog::APPEND_ONLY_TABLES` (tests verify both). Cross-schema foreign keys are fine (`ON DELETE RESTRICT`).
* `bin/console doctrine:migrations:migrate -n` (dev), `bin/test-db <token>` (test). `MigrationsGrantTest` migrates up/down/up in a throw-away database: write a correct `down()`.
* Types: ids are `uuid` (typed id class + `AbstractIdType`), money is the `Money` embeddable (`#[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]` → `amount_minor bigint`, `amount_currency char(3)`), time is `timestamptz` (`datetimetz_immutable`) with `Psr\Clock\ClockInterface` (no `new \DateTime()`, PHPStan `NoNativeDateTimeRule` enforces it in `src/`).
* `MappingMatchesDatabaseTest` checks entity ↔ table/column/nullability. (`doctrine:schema:validate` only for `--skip-sync`: hand-written CHECKs/FKs/partial indexes make the sync check noisy.)

## 4. Definition of done for a module feature

1. Unit tests for the domain; integration test for each command (including the emitted domain event) and each HTTP route (status codes, problem details, 401/403/404 for a foreign tenant); scenario tests where several modules interact.
2. Black-box tests in `tests/Api/<Module>` for every public endpoint: one happy path and one error path (validation / 401 / 403 / foreign tenant 404). Needed bootstrap data: extend `bin/api-test` + `ApiFixtures`.
3. `bin/check` green (cs, PHPStan max, deptrac, warm-up, unit, integration, scenario), `bin/api-test` green.
4. Endpoints have `#[OA\…]` attributes; admin endpoints have `#[RequiresPermission]`.
5. New personal data: retention class in `config/data-retention.yaml` (when it exists), not in logs, not in integration events, not in audit.

## 5. Troubleshooting

* *`Cannot autowire … excluded in services.php`*: a Domain/Contract interface needs `#[AsAlias]` on its implementation.
* *`Module boundary violation …`*: you injected a non-Contract service of another module; call its `Contract` facade or subscribe to its event.
* *`Handler … has no fromTransport` / `… has no subscriber`*: see integration events above.
* *`relation "…" does not exist` in tests*: run `bin/test-db <token>` after pulling/adding migrations; qualify the table with its schema.
* *Stale behaviour in `php-api`*: worker mode keeps the kernel in memory; `docker compose restart php-api worker-api`.
