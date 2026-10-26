# Eventor

Webová platforma pro pořadatele akcí: registrace, platby převodem (přes Pairrot) i hotově, komunikace s účastníky, program, odbavení, právní dokumenty a souhlasy, obsah po akci.

Architektura je **modulární monolit**: Symfony 8.1 na PHP 8.4 (FrankenPHP) nad PostgreSQL 17, transakční outbox nad Symfony Messengerem a append-only ledger pro peníze. Frontend v Next.js mluví výhradně přes HTTP API.

## Rozcestník

| Kam | Co tam najdete |
|---|---|
| [Zadání V3](docs/initial-idea/zadani-event-platforma.md) | zdroj pravdy o chování: feature ID (`REG-01` …) a scénáře S01–S18 |
| [Kniha architektury](docs/architecture/README.md) | cílová architektura po kapitolách: moduly, data, klíčové cesty, stavové automaty, asynchronní svět, integrace, kvalita, rizika, plán |
| [Slovník](docs/architecture/00-slovnik.md) | pojmy domény a jejich význam v kódu |
| [ADR](docs/adr/index.md) | architektonická rozhodnutí (anglicky) |
| [Podklady k rozhodnutí](docs/architecture/navrhy/00-architektonicke-smery.md) | zvažované směry a proč vyhrál ten současný |
| [Pravidla stavby](docs/implementation/README.md) | vlny W0–W5, testovací vrstvy, definice „hotovo“ |
| [Kontrakty](docs/implementation/contracts.md) | integrační události a smlouvy mezi moduly |
| [API průvodce](docs/implementation/api-guide.md) | jak volat API z frontendu: auth zóny, CSRF, scopes, problem details, idempotence |
| [Další kroky](docs/implementation/next-steps.md) | co zbývá, otevřené dluhy, priority |
| [Backend](backend/README.md) | spuštění stacku, služby a porty, testy, nástroje (anglicky) |
| [Frontend](frontend/README.md) | Next.js aplikace pořadatele, **zatím demo** |

## Struktura repozitáře

```
backend/    Symfony aplikace, moduly v src/<Modul>/, testy všech vrstev
frontend/   Next.js 16 aplikace pořadatele (rozpracované demo)
docs/       zadání, kniha architektury, ADR, implementační dokumenty
docker/     Docker image a konfigurace
bin/        skripty pro testy a bootstrap (check, api-test, test-db, pairrot-bootstrap)
Makefile    tenké obaly nad bin/*
```

## Rychlý start

Jediný předpoklad je Docker; PHP se na hostu neinstaluje.

```sh
make up                # celý stack: postgres, migrace, php, worker, php-api, smtp4dev, minio, pairrot
make pairrot           # jednou: postaví Pairrot a zapíše klíče do backend/.env.local
make test              # cs, PHPStan, deptrac, unit, integrační, scénáře, black-box API
```

| Služba | URL |
|---|---|
| API (dev) | http://localhost:8080 |
| API pro black-box testy | http://localhost:8081 |
| Frontend (demo) | http://localhost:3000 |
| smtp4dev | http://localhost:5000 |
| MinIO konzole | http://localhost:9001 |
| Pairrot | http://localhost:8090 |

Podrobnosti, založení prvního pořadatele a řešení kolizí portů jsou v [backend/README.md](backend/README.md).

## Stav

Backend má hotové vlny W0–W3 a z W4 modul Content. Zbývá Reporting, scénáře S01–S18, demo data a zpevnění (podrobně v [dalších krocích](docs/implementation/next-steps.md)). Frontend je rozpracované demo: navigace a obrazovky nad ukázkovými daty. Na backend jsou napojené jen přihlášení, výběr organizátora a založení jednoduché akce.
