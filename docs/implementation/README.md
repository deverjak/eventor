# Implementace Eventoru — cíl a pravidla stavby

> Řídicí dokument stavby. Architekturu popisuje [kniha](../architecture/README.md), rozhodnutí [ADR](../adr/), chování [zadání V3](../initial-idea/zadani-event-platforma.md). Tento soubor říká **co stavíme teď, v jakém pořadí a podle jakých pravidel pracují agenti**. Stav: 25. 10. 2026.

## Cíl

1. **Nejdřív API.** Funkční, testovatelné HTTP API backendu (Symfony 8.1, PHP 8.4, FrankenPHP, PostgreSQL 17) pro rozsah P0 podle [kap. 12](../architecture/12-plan-implementace/README.md) (fáze F0–F6), věrné knize: modulární monolit, schéma na modul, outbox nad Messengerem, ledger, value resolvery, compiler passy, dekorátory.
2. **Pak Next.js 16 frontend** (`frontend/`), který mluví výhradně přes API a generovaného TS klienta z OpenAPI.
3. **Vše běží v Dockeru.** PHP lokálně není. Pairrot se staví z `../chytre-digital/pairrot` (proměnná `PAIRROT_PATH`). `pairrot-sandbox` (simulátor příchozích plateb) autor teprve dodělá; do té doby se příchozí platba v testech simuluje podepsaným webhookem.

**Hotovo znamená:** `docker compose up` zvedne celý systém, `make test` (nebo `bin/test`) projde všechny vrstvy testů zeleně, `cache:warmup` hlídá architekturu compiler passy, deptrac a PHPStan jsou čisté.

## Testy — každá část má své testy

| Vrstva | Složka | Co | Prostředí |
|---|---|---|---|
| Unit | `backend/tests/Unit/<Modul>` | doména bez frameworku | bez DB |
| Integrační | `backend/tests/Integration/<Modul>` | handler + repozitář + DB + outbox; `WebTestCase` pro HTTP (status kódy, problem details, 401/403/404, tenancy) | Postgres, dama rollback |
| Architektura | `backend/tests/Integration/Architecture` | `TableOwnershipTest`, `CrossSchemaSqlTest`, `SchemaGrantsTest`, `MigrationsGrantTest`, `IntegrationEventRoutingTest`, `CommandEmitsEventTest`, `TenantIsolationTest` | Postgres |
| Scénáře | `backend/tests/Scenario` | S01–S18 napříč moduly | Postgres, in-memory transporty, `MockClock`, `InMemoryPaymentGateway` |
| Kontrakt | `backend/tests/Contract/Pairrot` | `HttpPairrotGateway` a podpis webhooku proti skutečnému Pairrotu v compose | `pairrot` kontejner |
| **API (black-box)** | `backend/tests/Api/<Modul>` | **skutečné HTTP požadavky** na běžící server (`api-test` služba v compose, vlastní DB, běžící worker). Žádný přístup ke kontejneru ani DB, jen HTTP. Data se zakládají přes API nebo `eventor:demo:seed`. Platba = podepsaný webhook poslaný testem. | `php-api` + `worker-api` + `pairrot` |

Black-box API sada je **akceptační test API pro frontend**: každý veřejný endpoint má aspoň jeden happy-path a jeden chybový test (validace / 401 / 403 / 404 cizího tenanta).

## Fáze stavby (API)

| Vlna | Obsah | Kdo | Paralelně |
|---|---|---|---|
| W0 · Platforma | compose, Docker image, Symfony skelet (`Eventor\` namespace), `Shared` kernel, Messenger (busy, middleware, transporty na modul, routing pass, retry, dead-letter), `ModuleBoundaryPass`, problem details, audit, idempotence, DB schémata a granty, Identity minimum (login, organizátor, membership, `CurrentOrganizerResolver`, voters + `PermissionMatrix`), testovací infrastruktura všech vrstev, deptrac, PHPStan, Pairrot v compose | 1 agent | ne |
| W1 · Kontrakty | `Contract/` všech modulů: fasády, DTO, integrační události z [katalogu](../architecture/06-asynchronni-svet/katalog-udalosti.md), veřejné výjimky | 1 agent | ne |
| W2 · Moduly I | Identity (zbytek), Catalog + formulář, Legal, Payments (ledger, Pairrot adaptér + dekorátory, webhook, reconcile), Audit + Attention | 4–5 agentů | ano |
| W3 · Moduly II | Registration + kapacity + samoobsluha, Communication, CheckIn | 3 agenti | ano |
| W4 · Celek | Content, Reporting, scénáře S01–S18, demo seed, OpenAPI export, doplnění black-box sady | 2–3 agenti | ano |
| W5 · Frontend | Next.js 16 (viz kap. 12 „Frontend“ jednotlivých fází) | později | |

Po každé vlně: review koordinátora, zelené testy, commit.

## Pravidla pro agenty (závazná)

1. **Vlastnictví souborů.** Agent mění jen `backend/src/<SvůjModul>/`, `backend/tests/*/<SvůjModul>/` a soubory, které mu zadání výslovně přidělí. Cizí modul používá jen přes jeho `Contract/`. Potřebuje-li změnu v cizím `Contract/` nebo ve `Shared`, napíše to do svého reportu, nemění to sám.
2. **Konfigurace patří modulu.** Služby, Messenger transport modulu, workflow a scheduler modulu se deklarují v `src/<Modul>/Infrastructure/Symfony/services.php` (přes `$container->extension('framework', …)`), ne do sdílených `config/packages/*`. Sdílené soubory (`security.php`, `composer.json`) mění jen koordinátor nebo agent, kterému to zadání přidělí.
3. **Závislosti.** `composer require` jen koordinátor. Chybějící balíček = požadavek v reportu.
4. **Migrace** jsou ručně psané, jedna nebo víc na modul, jméno `VersionYYYYMMDDHHMMSS`, popis začíná jménem modulu. Tabulky jen ve schématu vlastního modulu.
5. **Testovací DB na agenta.** Integrační testy běží s `TEST_TOKEN=<modul>` (DB `eventor_test_<modul>`), aby se paralelní agenti nepraly. Příprava: `bin/test-db <modul>`.
6. **Spouštění.** Vše přes `docker compose exec php …` (nebo `docker compose run --rm php …`). Nikdy neinstalovat PHP lokálně.
7. **Hotovo pro modul** = [definice hotového z kap. 10](../architecture/10-kvalita-a-testovani/README.md#definice-hotového-pro-každou-feature): unit + integrační + black-box API testy zelené, PHPStan a deptrac čisté, `cache:warmup` projde, endpointy mají OpenAPI popis.
8. **Rozpor s knihou** se neřeší potichu: agent zvolí řešení nejbližší knize a zapíše ho do reportu (koordinátor ho přenese do kap. 11 / ADR).
9. **Necommitovat.** Commity dělá koordinátor po review.
10. **Report** na konci: co je hotovo (feature ID), co ne, jak spustit testy, výsledky testů (počty), odchylky od knihy, požadavky na koordinátora.

## Struktura repozitáře

```
eventor/
├─ compose.yaml               php, worker, php-api + worker-api (black-box testy), postgres, smtp4dev, minio, pairrot
├─ docker/                    Dockerfile FrankenPHP, init skripty Postgresu
├─ backend/
│  ├─ src/Shared/             kernel: Money, Clock, Id, Messenger, compiler passy, tenant, problem details
│  ├─ src/<Modul>/            Domain · Application · Infrastructure/Symfony · UserInterface/{Http,EventHandler} · Contract/{Event,Exception}
│  ├─ migrations/
│  └─ tests/                  Unit · Integration · Scenario · Contract · Api
├─ frontend/                  Next.js 16 (W5)
└─ docs/                      kniha, ADR, tento dokument
```
