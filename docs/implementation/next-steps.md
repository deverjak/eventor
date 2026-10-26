# Eventor — stav a další postup

> Stav k 26. 10. 2026. Navazuje na [README](README.md) (cíl a pravidla stavby), [contracts.md](contracts.md) (kontrakty modulů) a [api-guide.md](api-guide.md) (API pro frontend). Odchylky implementace od knihy jsou v [kap. 11](../architecture/11-rizika-a-kompromisy/README.md#odchylky-implementace-od-návrhu-w0w4).

## Kde jsme

| Vlna | Obsah | Stav |
|---|---|---|
| W0 | platforma, Docker, Shared kernel, testovací infrastruktura | hotovo |
| W1 | kontrakty všech modulů | hotovo |
| W2 | Identity, Catalog, kapacity, Legal, Payments, Audit, Attention | hotovo, dvě kola nezávislého review opravena |
| W3 | Registration, Communication, CheckIn | hotovo |
| W4 | **Content hotovo**; Reporting, scénáře, demo, zpevnění zbývají | rozpracováno |
| W5 | Next.js frontend | dělá autor (`frontend/`) |

Ověřeno v izolované kopii (bez rozpracovaných souborů a bez potíží bind mountu): php-cs-fixer, PHPStan max 0, deptrac 0, warmup prod i test, unit, integrační (včetně architektonických testů `TenantIsolationTest`, `EventScopeTest`, `ModuleBoundaryPass`, routing událostí) a scénáře; black-box API sada proti běžícímu `php-api` a workeru.

## Zbytek W4 (P0, fáze F6 kap. 12) — v tomto pořadí

1. **Reporting** (ADM-01, 02, 06–08): přehledy organizátora a akce, správa registrací napříč moduly, CSV exporty respektující oprávnění a omezení na akce, report po akci, provozní přehled odbavení (CHK-10, `CheckInDashboardQuery`). Odebírá 10 událostí, které ho mají v `#[PendingSubscribers]`; projekce v schématu `reporting`. Exporty: žádné osobní údaje mimo role, které je smí vidět.
2. **Scénáře S01–S18** jako jedna sada v `tests/Scenario`: dnes jsou pojmenované S01, S02, S09, S10, S12, S13 a pokryté uvnitř testů S03–S08, S11, S15. Doplnit S14, S16, S17 (S18 je P1) a celý storyboard dema (kap. 13, kroky 1–6 a odbočky).
3. **Demo data** `eventor:demo:seed` — „Borský podzimní víkend 2026“ (`BorskyPodzimniVikendStory`): organizátor, tým, akce s aktivitami, formulář, právní dokumenty, objednávky v různých stavech, platby, odbavení, galerie. Nasadit do `bin/api-test` jako výchozí data black-box sady a pro frontend.
4. **Zpevnění**
   - souhrnná migrace cizích klíčů napříč schématy (`ON DELETE RESTRICT`), odložená z review W3;
   - `EVENTOR_STRICT_SUBSCRIBERS=1`: po Reportingu rozhodnout zbylé `#[PendingSubscribers]` mimo `reporting`:
     - `catalog` u `capacity_changed`, `event_details_changed`, `event_published`, `registration_state_changed`, `pairrot_connected` a `capacity_released` (cache veřejné stránky a dostupnosti, kontrola převodu po připojení Pairrotu),
     - `identity` u `membership_changed`,
     - P1: `registration` u `registration_state_changed` a `participations_confirmed` (čekací listina), `communication` u `waitlist_offer_*`, `legal` u `participation_transferred`, `privacy` u `event_completed`;
     P1 odběry označit `#[NoSubscribers]` nebo nechat v pending s výjimkou v přísném režimu;
   - OpenAPI export do souboru pro generovaný TS klient frontendu (`bin/console nelmio:apidoc:dump`), kontrola, že každý endpoint má odpovědi 4xx;
   - `#[Target]` u pojmenovaných autowiring aliasů (Symfony 8.1 deprecations) a nový formát konfigurace flysystem-bundle.
5. **Uzavření P0 (milník M2)**: celá kontrola + black-box sada zeleně, aktualizace knihy (kap. 11, katalog událostí, ADR poznámky), přegenerování diagramů a PDF.

## Otevřené dluhy z dosavadních vln

- Catalog: automatické otevírání a zavírání registrací podle termínu (Scheduler) a automatické dokončení akce chybí, jde to jen ručně.
- EVT-12: zrušení akce zavře registrace a pošle událost; úkoly k vrácení peněz se zatím zakládají ručně.
- Attention: akce, které mění cizí modul (obnova účasti, vrácení peněz, opakování doručení), jsou v položkách jen odkazy; endpointy už existují (Registration restore, Communication retry), stačí je zapojit. Chybí typy `check_in_escalation`, `data_subject_request`, `likeness_review`.
- Communication: odebrání šablony a zrušení potlačení se neaudituje.
- Payments: denní limit reconcile není; `PaymentRequest` nemá Symfony workflow.
- Content: viditelnost „jen registrovaní“ (POST-03) a vazba na mediální souhlasy (POST-06) jsou P1.

## P1 (fáze F7, až po M2)

Pořadí podle hodnoty pro demo: čekací listina (REG-12) → slevy a doplňky (EVT-15, EVT-09) → kategorie a vlny (EVT-07/08, CAP-01 vlna) → změna varianty a převod účasti (S18, REG-09 plná, REG-10, LEG-12) → osobní údaje (PRV-01–05, anonymizace, retence) → týmová varianta (REG-04, CAP-07) a drobnosti (ORG-04, PUB-05, REG-13, PAY-12, CHK-11, POST-03/06, COM-09, …).

## Jak pracovat dál (poučení z W0–W4)

- **Ověřovat v izolované kopii**, ne ve sdíleném stromu: `git worktree` = `main` + soubory dávky, zkopírované do Docker volume a zkontrolované tam (cs, PHPStan, deptrac, warmup, unit, integrace, scénáře). Bind mount na Windows pod zátěží hlásí falešné I/O chyby a rozpracované soubory jiných pracovníků rozbíjí sestavení.
- **Commitovat z izolované kopie**: `git update-ref` + `git reset` v hlavním stromu, aby šly commitnout jen změny jedné dávky i u sdílených souborů (např. `#[PendingSubscribers]`).
- **Paralelně nejvýš 1–2 pracovníci** na sdílený strom; víc vede ke kolizím, OOM v Dockeru a limitům.
- **Codex CLI** (`gpt-5.6-terra`) zvládne za kolo jen omezený kus práce a dlouhé běhy v Dockeru nedotahuje: dávat mu malé, uzavřené úkoly (review, dokumentace, jeden soubor), testy spouští koordinátor a vrací mu konkrétní chyby.
- **Testovací past**: Symfony po každém HTTP požadavku vyprázdní in-memory transporty — po požadavku, který publikuje integrační událost, hned `drain()`.
- **Pravidla knihy platí pro každý nový modul**: use case složky, `*Query` + typované `*View`, `#[MapRequestPayload]` vstupy s validací, admin routy s `{eventId}` (omezení na akce), entity `TenantScoped` s doménovými událostmi a auditem, `#[PendingSubscribers]` odebrat při psaní odběratele.
