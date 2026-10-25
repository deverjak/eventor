# Architektonické směry — varianty k rozhodnutí

**Stav:** návrh k review · **Datum:** 24. 10. 2026 · **Vstupy:** [zadání V3](../../initial-idea/zadani-event-platforma.md), kontrakt Pairrotu (`chytre-digital/pairrot`, README + `docs/webhooks-receiver.md`), zkušenosti z main-panelu (`chytre-digital/main-panel/docs/architecture`, hlavně kapitoly 06, 08 a 09)

Tento dokument nepředstavuje solution architecture. Popisuje čtyři celkové směry a sedm samostatných rozhodovacích os. Ke každé ose dává doporučení. Až se směry odsouhlasí, vznikne z nich architektonická kniha (`docs/architecture/01–10`), diagramy a PDF.

---

## 1. Co architekturu nejvíc tlačí

Ze zadání vychází, že CRUD tvoří menší část práce. Rozhodující jsou tyto vlastnosti:

| Tlak | Odkud | Důsledek pro architekturu |
|---|---|---|
| Atomická kapacita napříč úrovněmi | CAP-02, S01, S02 | Registrace, kapacita a potvrzení musí proběhnout v **jedné DB transakci**. Proto žádné mikroslužby uprostřed registrace. |
| Peníze jako neměnné události | §4 invarianty, PAY-08, S03, S07, S11 | Finanční stav je **projekce z účetní knihy** (append-only ledger). Z pohledu peněz jde v praxi o event sourcing. |
| Asynchronní svět se selháními | PAY-03, COM-07, S08, S09 | Je potřeba **transakční outbox**, retry s backoffem a dead-letter, který se promítne do fronty výjimek. |
| Oddělené stavové dimenze | kap. 5 | Pro každou dimenzi zvlášť jeden **state machine** (účast, zveřejnění, registrace, odbavení, doručení, potvrzení). Žádné sdílené pole `status`. |
| Verzování a historičnost | FRM-08, LEG-03, S14, S17 | Snapshoty schémat a dokumentů, obsahově adresované soubory (hash) a odpovědi s uloženou verzí formuláře. |
| Multitenance a jemná oprávnění | kap. 3, NFR | Tenant scope na úrovni dat a autorizace, matice role × událost a redukované projekce pro odbavovací roli. |
| Showcase Symfony | inzerát | Architektura musí přirozeně, ne násilně, použít compiler passy, dekorace, value resolvery, tagged services, Messenger, Workflow, Scheduler a Security voters. |

### Co z Pairrotu mění návrh

Ze skutečného kontraktu Pairrotu (ne z předpokladů v zadání, kap. 7.7) plyne:

1. **Automatické párování funguje jen na přesnou částku.** Částečné platby a přeplatky (S04, S07) vznikají v Pairrotu jen **ručním přiřazením transakce**. Eventor proto musí umět zobrazit nespárované transakce (`transaction.recorded` s `matching.result`) a přiřazení vyvolat přes Pairrot API (`POST …/transactions/{id}/allocations`). Rekonciliační fronta PAY-06 tak dostává reálný obsah.
2. **Webhook nenese platbu, ale nový stav požadavku** (`previous` / `current`, `paid_amount`, `sequence`). Platební událost v ledgeru eventoru tedy vzniká jako **delta `paid_amount`**. Deduplikuje se podle `event.id` a mimo pořadí doručené změny ochrání monotónní `sequence`. Událost `allocation_revoked` vede na kompenzační zápis se zápornou částkou.
3. **Pairrot zkouší webhook doručit zhruba hodinu, pak to vzdá.** Proto je nutný reconcile job nad event logem (`GET /v1/management/webhook-events`), stejně jako v main-panelu.
4. **`due_at` v Pairrotu nic neexpiruje.** TTL rezervace vlastní výhradně eventor.
5. **`external_id` je idempotenční klíč vytvoření požadavku** (stejné tělo vrátí `200 replayed`, jiné tělo `409`). Retry vytvoření (PAY-03, S08) je tedy bezpečný bez vlastní deduplikace.
6. **Pro testy neexistuje simulace příchozí platby.** Eventor proto potřebuje vlastní **Pairrot simulátor**, tedy adaptér za stejným portem, který podepisuje webhooky stejně jako Pairrot. Bez něj demo storyboard (kap. 13) nejde odehrát.
7. **Tenancy:** organizátor odpovídá subtenantovi Pairrotu `eventor_org_<uuid>`. Eventor má vlastní root s management klíčem a jeden webhook endpoint se `scope: own_and_direct_children`.

---

## 2. Čtyři celkové směry

### Směr A — „Symfony by the book“: vrstvený monolit

Controller → Service → Doctrine entita, CRUD přes API Platform a logika v servisách a Doctrine listenerech.

- **Pro:** nejrychlejší start, nejmenší množství kódu.
- **Proti:** pravidla se rozpadnou do listenerů a servis. Atomicita kapacity, outbox a ledger se budou dolepovat. U pohovoru demonstruje Symfony jen jako framework, ne jako DI kontejner a architektonický nástroj. Scénáře S03–S09 jsou v tomto směru přesně ta místa, kde vrstvený CRUD praská.
- **Verdikt:** nedoporučuji. Hodí se jen jako baseline pro srovnání.

### Směr B — Modulární monolit s hexagonálními moduly *(doporučeno)*

Jedna Symfony aplikace a jedna Postgres databáze. Uvnitř je asi 10 modulů (bounded contexts) se striktními hranicemi, které hlídá **deptrac**. Každý modul má vrstvy `Domain / Application / Infrastructure / UserInterface`. Moduly komunikují přes:

- **synchronní veřejné API modulu** (fasáda nebo query), pokud je potřeba sdílená transakce (Registrace ↔ Kapacity ↔ Právní potvrzení),
- **doménové události přes transakční outbox**, pokud jde o reakci (Platby → Registrace, cokoli → Komunikace, cokoli → Audit).

Peníze jsou ledger s odvozeným stavem. Stavové dimenze řídí Symfony Workflow. Plánované věci (expirace, upomínky, reconcile) obstarává Symfony Scheduler.

- **Pro:** odpovídá tlakům z kapitoly 1 jedna k jedné. Sdílená transakce zůstává tam, kde je nutná, a asynchronnost tam, kde je přirozená. Showcase Symfony přichází přirozeně. Main-panel se k tomuto tvaru dostával zpětně (ADR 009) a eventor ho může mít od prvního commitu.
- **Proti:** víc „ceremonie“ (porty, adaptéry, DTO) a delší rozjezd než u směru A.
- **Verdikt:** doporučuji jako základ.

### Směr C — B + CQRS a event sourcing objednávky a účasti

Jako B, ale agregáty `Order` a `Participation` se ukládají jako proud událostí (Patchlevel / EventSauce / Ecotone) a čtení obstarávají projekce.

- **Pro:** maximální auditovatelnost a „wow“ efekt. Otázka 5 zadání (audit vs. ES) dostane radikální odpověď.
- **Proti:** atomická kontrola kapacity napříč agregáty v ES vyžaduje rezervační sagu nebo sdílený counter, takže S02 se komplikuje. Výmaz osobních údajů (PRV-04) v neměnném proudu vyžaduje crypto-shredding. Časově náročné a riziko, že demo nebude dotažené.
- **Verdikt:** nedoporučuji v plném rozsahu. Doporučuji **selektivní event sourcing**: ledger plateb je de facto event-sourced (stav je fold neměnných událostí) a zbytek drží stav s append-only auditem. V dokumentaci to bude ADR s explicitním zdůvodněním. To je pro pohovor silnější argument než ES všude.

### Směr D — Distribuované služby

Registrace, Platby a Komunikace jako samostatné Symfony aplikace propojené přes RabbitMQ.

- **Proti:** S02 a S13 (jedna objednávka, jedna transakce) by vyžadovaly sagy s kompenzacemi. Provozní náklady neodpovídají jedné instanci dema. Zadání nikde nežádá nezávislé škálování.
- **Verdikt:** zamítnuto. Zdokumentuje se v ADR jako zvážená alternativa a k ní cesta, jak by se modul z B dal vyjmout (Payments je první kandidát, protože už teď mluví jen přes outbox a port).

---

## 3. Rozhodovací osy (nezávislé na směru)

### Osa 1 — Frontend

| Varianta | Popis | Pro / proti |
|---|---|---|
| **1a Twig + Symfony UX** (Turbo, Live Components, Stimulus) | vše v jedné aplikaci | jeden stack, ukazuje Symfony i na frontendu / API-01 (dokumentované API pro další klienty) se pak dělá navíc, UX formulářového editoru v Live Components je pracné |
| **1b API-first backend + SPA** (React/Next.js nebo Vite) | Symfony jen jako API | čisté API-01, odbavovací klient jde na stejné API / dva projekty, víc práce |
| **1c Hybrid** | veřejná stránka, registrace a odbavení v Twig + UX, administrace a editor formuláře jako SPA nad API | nejlepší UX pro obě strany / dvě technologie na frontendu |

Doporučení: **1b**. Backend je to, co se hodnotí. Čisté API zároveň splňuje API-01 a API-04 a frontend pak nemůže obejít doménová pravidla. Konkrétní technologii frontendu je potřeba rozhodnout (viz otázky).

### Osa 2 — Styl API

- **2a API Platform** (state providers a processors): rychlé, OpenAPI zdarma. Doménové příkazy (storno, převod, odbavení) se do zdrojů mapují neohrabaně a velká část DI se skryje za konvence.
- **2b Ručně psané controllery**: tenké controllery s `#[MapRequestPayload]` DTO, vlastními **value resolvery** (tenant, token samoobsluhy, QR token, aktuální člen týmu) a odesláním příkazu na command bus. OpenAPI dodá NelmioApiDocBundle.
- **Doporučení: 2b.** Příkazově orientované API odpovídá doméně a value resolvery jsou přímo v požadavcích inzerátu.

### Osa 3 — Konzistence kapacit (otázka 2 zadání)

| Varianta | Mechanismus | Hodnocení |
|---|---|---|
| 3a Pesimistické zámky | `SELECT … FOR UPDATE` řádků všech kapacitních úrovní v deterministickém pořadí | korektní, ale držení zámků po celou transakci registrace |
| **3b Podmíněný atomický counter** | tabulka `capacity_bucket` (jeden řádek na úroveň: událost / aktivita / varianta / vlna) a `UPDATE … SET held = held + n WHERE id = ? AND held + confirmed + n <= limit`. Nula ovlivněných řádků znamená obsazeno a rollback celé transakce. Pořadí podle `id` brání deadlockům. Navíc `CHECK (held + confirmed <= limit OR overbooked_allowed)` jako poslední pojistka. Příznak `overbooked_allowed` nastavuje jen vědomé snížení kapacity podle CAP-06. | krátké zámky, jedna SQL operace na úroveň, S01 a S02 přirozeně (vše v jedné transakci) |
| 3c Optimistické verze | `version` na bucketu, retry při konfliktu | při náporu otevření registrací (NFR výkon) vede ke kaskádě retry |

Doporučení: **3b**. Pravdivost counterů hlídá rekonciliační dotaz (součet účastí vůči bucketu) jako test a jako noční kontrola. Pro CAP-06 (snížení kapacity pod obsazenost) se kontrola provádí jen při navyšování, takže snížení projde s varováním.

### Osa 4 — Asynchronní páteř

- **4a Messenger s Doctrine transportem napřímo:** handlery dispatchují zprávy přímo. Hrozí ztráta zprávy, pokud transakce spadne po dispatchi, nebo naopak.
- **4b Transakční outbox s per-subscriber delivery** (vzor z main-panelu): doménová událost se zapíše do `outbox_event` ve stejné transakci. Relay (Messenger worker) z ní vytvoří doručení pro každého odběratele, s leasem, `SKIP LOCKED`, backoffem 1 m / 5 m / 30 m / 2 h / 12 h a dead-letterem, který se promítne do výjimky ve frontě ADM-03.
- **Doporučení: 4b**, implementovaný jako Messenger middleware a vlastní transport. Infrastruktura zůstává jen Postgres (Redis nebo RabbitMQ jako volitelný transport bez zásahu do domény).

### Osa 5 — Audit vs. event sourcing (otázka 5)

**Doporučení:** append-only `audit_entry` (kdo / kdy / co / před → po / důvod, rozlišení systém × člověk × účastník přes token) a ledger plateb jako jediné event-sourced místo. Neměnnost se vynucuje na úrovni DB (`REVOKE UPDATE, DELETE, TRUNCATE` aplikačnímu uživateli) a hlídá ji test migrací. Tím se předchází bolesti main-panelu (09: „append-only, které není append-only“).

### Osa 6 — Dynamický formulář (otázka 6)

- Schéma formuláře je neměnný **JSONB snapshot** pro každou verzi (`form_version`).
- Odpovědi jsou **JSONB s typovanými hodnotami**, mají scope (objednávka / osoba / účast / skupina) a nesou `form_version_id`. Sémantická pole (jméno, datum narození, e-mail, zákonný zástupce) se navíc **projektují do typovaných sloupců** entity `Person`, protože se nad nimi počítá věk, kategorie a adresování.
- Validace jde z jednoho zdroje pravdy: `FormSchema` → strom Symfony Validator constraints (každý typ pole má tagovaný `FieldType`). Frontend dostává totéž schéma a z něj odvozuje UI.
- Filtrování podle vlastních polí (ADM-04/05) zajišťuje GIN index nad JSONB.

### Osa 7 — Multitenance (otázka 9)

- Sdílená DB s `organizer_id` na každé tenant-scoped tabulce.
- Tenant se vždy bere z URL (`/api/org/{orgSlug}/…`), nikdy z ambientního stavu (lekce z main-panelu, ADR 005).
- Doctrine **SQL filter** se zapíná ve value resolveru tenanta. Autorizaci obstarávají Security **voters** nad maticí role × událost.
- Druhá linie (volitelně, P1): Postgres RLS s `SET LOCAL app.organizer_id`. Doporučuji ji zahrnout jako ADR „zvážená, odložená“.

---

## 4. Mapa showcase Symfony (doporučený směr B)

Každý mechanismus má v doméně reálný důvod. Nikde nejde o ukázku pro ukázku.

| Mechanismus | Kde v eventoru | Proč tam |
|---|---|---|
| **Compiler pass** | `FieldTypeRegistryPass`: posbírá tagované typy polí formuláře a při kompilaci ověří, že každý má validátor, normalizer i JSON Schema fragment | chyba konfigurace spadne při `cache:warmup`, ne v produkci |
| | `DomainEventSubscriberPass`: z atributu `#[AsOutboxSubscriber]` sestaví mapu událost → odběratelé pro outbox relay a ověří, že každá událost z katalogu má verzi a aspoň jednoho odběratele nebo je explicitně „fire-and-forget“ | katalog událostí je ověřený kódem (main-panel 09: drift manifestu) |
| | `ModuleBoundaryPass`: zakáže, aby služba z modulu A přímo injektovala neveřejnou službu modulu B (doplněk k deptracu na úrovni kontejneru) | hranice modulů hlídá i DI |
| **Service decoration** | `PaymentGateway` (port) ← `HttpPairrotGateway` dekorovaný `RetryingGateway` → `CircuitBreakerGateway` → `PiiRedactingLoggingGateway` | odolnost i logování bez osobních údajů (NFR) bez zásahu do adaptéru |
| | `AvailabilityReader` ← `CachedAvailabilityReader` (cache s tag invalidací při změně kapacity) | výkon veřejné stránky při náporu |
| | Messenger bus ← middleware `TenantContextMiddleware`, `AuditMiddleware`, `IdempotencyMiddleware` | průřezové koncepty na jednom místě |
| **Value resolvers** | `#[CurrentOrganizer]`, `#[CurrentMember]`, `#[SelfServiceToken] Order`, `#[CheckInToken] Participation`, `#[IdempotencyKey]` | autorizace a dekódování tokenů před controllerem, controller zůstává tenký |
| **Tagged services, locators** | `#[AutowireIterator('eventor.field_type')]`, `#[AutowireLocator]` pro handlery výjimek (matice CHK-09), strategie plateb (převod / hotovost / zdarma) | otevřené rozšíření bez `switch` |
| **Bindings, aliasy, `#[Target]`** | `bind: $pairrotWebhookSecrets`, tři busy (`command.bus`, `query.bus`, `event.bus`) přes `#[Target]`, přepínání `PaymentGateway` mezi `http` a `simulator` aliasem podle env | konfigurace bez podmínek v kódu |
| **Vlastní env var processor** | `%env(decrypt:ORG_SECRET_KEY)%`, popř. šifrování Pairrot credentials v DB přes vlastní DBAL typ | NFR: šifrování citlivých konfigurací |
| **Workflow** (state_machine) | účast, zveřejnění události, registrace, odbavení, doručení zprávy, právní potvrzení; guardy přes voters a listener `workflow.completed`, který vytvoří audit a outbox událost | oddělené stavové dimenze z kap. 5 |
| **Scheduler** | expirace rezervací, upomínky (PAY-09), reconcile Pairrotu, retry outboxu, retenční úlohy | plánované procesy bez cronu v OS |
| **Security** | vlastní authenticator pro tokeny samoobsluhy (API-04), access token handler pro odbavovací klienty, voters, RateLimiter na veřejnou registraci | API-03, API-04 |
| **Doctrine** | embeddable `Money`, vlastní DBAL typy, SQL filter tenanta, částečný unique index „právě jedno platné odbavení“ (S10), `CHECK` constrainty v migracích | invarianty v DB jako poslední linie |
| **Kvalita** | deptrac, PHPStan max a vlastní pravidla (zákaz `new \DateTime`, zákaz float u peněz), PHPUnit; scénáře S01–S18 jako integrační testy a souběh přes paralelní procesy | testovatelnost (NFR, otázka 16) |

---

## 5. Návrh bounded contextů (otázka 1)

| Modul | Sdílená transakce s | Komunikace ven |
|---|---|---|
| Identity & Organizer (ORG, oprávnění) | — | události `MemberInvited` |
| Catalog (EVT, PRG, FRM schémata) | — | `ActivityRescheduled`, `ProgramChanged`, `FormVersionPublished` |
| Legal (LEG dokumenty a potvrzení) | **Registration** (potvrzení při registraci) | `DocumentVersionPublished` (reconfirm) |
| Registration & Capacity (REG, CAP) | **Legal**, Capacity uvnitř | `OrderPlaced`, `ParticipationExpired`, … |
| Payments (PAY, ledger, Pairrot port) | — | konzumuje `OrderPlaced`; emituje `OrderSettled`, `PaymentReceivedLate` |
| Check-in (CHK) | — | `ParticipationCheckedIn` |
| Communication (COM) | — | jen konzumuje (outbox subscriber) |
| Content (POST) | — | — |
| Privacy (PRV, retence) | — | konzumuje události kvůli retenci |
| Audit + Exceptions (AUD, ADM-03) | lokálně v každé transakci (audit), fronta výjimek | konzumuje dead-letter |
| Reporting (ADM read modely) | — | čte projekce |

Synchronně spolu drží jen **Registration + Capacity + Legal**. Všechno ostatní reaguje přes outbox. Payments s Registration komunikují obousměrně, ale asynchronně: objednávka → požadavek a platba → potvrzení účastí.

---

## 6. Lokální prostředí a dokumentace

- **Docker Compose:** `php` (FrankenPHP nebo PHP-FPM + Caddy, PHP 8.4, Symfony 7.4 LTS), `worker` (Messenger consumer + Scheduler), `postgres:17`, `mailpit` (zachytávání e-mailů, S09 přes vypnutí mailpitu), `minio` (S3 úložiště souborů), volitelně `frontend`. Na Windows není nutné nic kromě Dockeru.
- **Pairrot:** výchozí je simulátor v eventoru (UI „přišla platba“ / „duplicitní webhook“ / „částečná úhrada“). Volitelný profil `pairrot-real` spustí skutečný Pairrot (.NET + Postgres) v Dockeru pro kontraktní test vytvoření požadavku.
- **Dokumentace:** kniha `docs/architecture/` ve struktuře main-panelu (00 slovník … 10, „Co si odnést“, „Jak to číst“), ADR v `docs/adr/`. Diagramy jako kód přes převzatý `dd.py` (diagram-design styl) a Playwright export do PNG. PDF celé knihy vznikne převzatým `book.mjs`.

---

## 7. Rozhodnuto (24. 10. 2026)

- **Frontend:** API-first Symfony backend + Next.js frontend (osa 1b).
- **API:** ručně psané controllery, `#[MapRequestPayload]`, vlastní value resolvery, NelmioApiDoc (osa 2b).
- **Pairrot:** eventor se píše proti **skutečnému API Pairrotu**. Simulaci plateb zajistí samostatná služba `pairrot-sandbox` (připravuje autor), proto **eventor žádný vlastní simulátor nemá**. In-memory fake portu zůstává jen pro unit a integrační testy.
- **Rozsah:** stavět **P0 + P1**.
- **Směr:** **B**, modulární monolit se stavovými agregáty a event-sourced ledgerem plateb. Zdůvodnění v [ADR 001](../../adr/001-modular-monolith-with-event-sourced-ledger.md), rozbor v [01 · B vs. C](01-smer-b-vs-c.md).
- **Verze:** Symfony 8.x + PHP 8.4. Konkrétně 8.1, protože 8.0 už nemá podporu. Na 8.2 se přejde po vydání v listopadu 2026.
- **Jazyk dokumentace:** kniha česky, ADR anglicky (jako main-panel). ADR 001 se při stavbě knihy přeloží do angličtiny.

- **Autentizace:**
  - tým: `json_login` + HttpOnly session cookie na stejné doméně (Next.js rewrite `/api/*`),
  - účastník: vlastní authenticator nad podepsaným tokenem s účelem a expirací, který jde odvolat,
  - odbavovací zařízení: `access_token` s vlastním handlerem (opaque token v DB). JWT se nepoužívá.

- **Nasazení:** lokálně Docker Compose. Produkční image (FrankenPHP) a compose pro VPS za Caddy jsou připravené, nasazení proběhne po dokončení.
- **Repozitář:** monorepo `backend/` (Symfony), `frontend/` (Next.js), `docs/`, jeden `compose.yaml`. TS klient se generuje z OpenAPI.
- **Databáze:** PostgreSQL 17 (partial unique indexy, JSONB + GIN, transakční DDL, RLS, `LISTEN/NOTIFY` pro Messenger).
- **E-mail:** Symfony Mailer. Lokálně smtp4dev, produkčně SMTP/API provider podle DSN.
- **Soubory:** Flysystem nad S3 API, lokálně MinIO.
