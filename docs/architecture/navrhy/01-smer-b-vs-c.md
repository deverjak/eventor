# Směr B vs. směr C — detailní rozbor

**Stav:** podklad k rozhodnutí · **Datum:** 24. 10. 2026 · navazuje na [00 · Architektonické směry](00-architektonicke-smery.md)

Oba směry sdílejí stejný základ: jedna Symfony aplikace, jeden Postgres, moduly podle bounded contextů, hranice hlídané deptracem, transakční outbox, API-first pro Next.js a skutečný Pairrot. Liší se v tom, **co je zdrojem pravdy o stavu objednávky a účasti**:

- **B:** zdrojem pravdy je aktuální stav v tabulkách. Historii nese append-only audit a ledger plateb, který je jako jediný event-sourced.
- **C:** zdrojem pravdy je proud událostí. Tabulky jsou jen projekce a dají se kdykoli zahodit a přehrát.

---

## Část I — Směr B: modulární monolit, stav + ledger

### B.1 Struktura kódu

```
src/
  Shared/                      # kernel: Money, Clock, Uuid, TenantId, AuditActor, Outbox, Bus rozhraní
  Identity/                    # organizátor, členové, role, tokeny
  Catalog/                     # událost, aktivita, varianta, vlna, program, formulář (schéma)
  Legal/                       # dokumenty, verze, požadavky, potvrzení
  Registration/                # objednávka, osoba, účast, kapacity, čekací listina, slevy
  Payments/                    # ledger, platební požadavek, rekonciliace, refund úkoly, Pairrot port
  CheckIn/
  Communication/
  Content/
  Privacy/
  Audit/                       # audit log + fronta výjimek
  Reporting/                   # read modely, exporty
```

Každý modul má stejné vnitřní členění:

```
Registration/
  Domain/            # entity, value objects, doménové události, rozhraní repozitářů, doménové služby (CapacityPolicy, PriceCalculator)
  Application/       # příkazy + handlery, dotazy + handlery, DTO; jediné místo, kde se otevírá transakce
  Infrastructure/    # Doctrine mapping (XML/atributy mimo doménu), repozitáře, adaptéry (PairrotHttpGateway…)
  UserInterface/     # HTTP controllery, value resolvery, CLI příkazy, outbox subscribery
  Api/               # veřejné rozhraní modulu pro ostatní moduly (fasáda + DTO); jediné, co smí jiný modul importovat
```

Deptrac pravidla: `Domain` nezávisí na ničem kromě `Shared/Domain`, `Application` jen na vlastní `Domain`, a cizí modul smí sahat jen do `*/Api`. Hranici na úrovni DI hlídá navíc `ModuleBoundaryPass`.

### B.2 Datový model

- Agregáty jsou **Doctrine entity s chováním** (ne anemické). `Order` drží kolekci `Person`, `Participation`, `OrderItem` a `GroupMembership`. Účast je vlastní entita uvnitř agregátu `Order`. Stav účasti se mění jen přes `Participation::confirm()`, `expire()` a podobné metody, které volá Symfony Workflow (marking store = sloupec `state`).
- Každá stavová dimenze má **vlastní sloupec a vlastní workflow**: `participation.state`, `participation.check_in_state` (odvozený z tabulky `check_in`), `event.publication_state`, `event.registration_state`, `activity.registration_state`, `delivery.state`, `confirmation.state`.
- **Kapacita:** tabulka `capacity_bucket(id, scope_type, scope_id, limit, held, confirmed)`. Účast obsazuje 2–4 buckety (událost, aktivita, varianta, P1 vlna) a vazbu drží `participation_capacity_claim`.
- **Ledger plateb (event-sourced část):** `ledger_entry(id, order_id, kind, amount_minor, source, external_ref, occurred_at, recorded_at, actor, reason, compensates_id)`. Druhy: `pairrot_allocation`, `pairrot_revocation`, `cash`, `cash_correction`, `manual_correction`, `refund_settled`. Finanční stav objednávky vzniká foldem `FinancialState::fromEntries($entries, $amountDue)`. Na objednávce je jen cache (`financial_state`, `paid_minor`, `ledger_version`), přepočtená ve stejné transakci, v jaké vznikl zápis.
- **Audit:** `audit_entry(id, organizer_id, actor_type[user|system|participant_token], actor_id, entity_type, entity_id, action, before jsonb, after jsonb, reason, correlation_id, occurred_at)`. Aplikační DB uživatel má jen `INSERT` a `SELECT` a test migrací kontroluje, že jiné granty nevznikly.
- **Outbox:** `outbox_event(id, type, version, aggregate_type, aggregate_id, organizer_id, payload, occurred_at, correlation_id)` a `outbox_delivery(event_id, subscriber, state, attempts, next_attempt_at, lease_until, last_error)`.

### B.3 Zápisová cesta: registrace (S01, S02, S13)

```
POST /api/public/events/{slug}/orders   (Idempotency-Key)
 └─ Controller (#[MapRequestPayload] PlaceOrderRequest, #[IdempotencyKey])
     └─ command.bus → PlaceOrderHandler                       ← jediná DB transakce
         1. načti publikovaný snapshot katalogu + aktuální form_version + platné verze dokumentů
         2. FormValidator: odpovědi vůči schématu (server, ne viditelnost)        FRM-07
         3. PriceCalculator: varianty, doplňky, slevy (P1) → Money               REG-05
         4. LegalRequirementResolver: co se pro tuto kombinaci vyžaduje, kdo potvrzuje,
            nezletilí → zákonný zástupce per dítě                              S13, LEG-11
         5. CapacityAllocator: pro všechny buckety všech účastí v deterministickém pořadí
            UPDATE capacity_bucket SET held = held + :n
             WHERE id = :id AND held + confirmed + :n <= "limit"
            jakýkoli 0 rows → CapacityExhausted → rollback celé transakce       S01, S02
         6. Order::place(...) → Persons, Participations (state=pending + hold_expires_at
            nebo confirmed u hotovosti/zdarma), Confirmations (verze dokumentu, osoba, účast)
         7. audit + outbox: OrderPlaced{orderId, paymentMethod, amountDue}
     COMMIT
 └─ 201 { orderId, selfServiceUrl, paymentStatus: "pending_instructions" }
```

Asynchronně za commitem:

```
outbox relay → Payments: CreatePaymentRequestOnOrderPlaced
    PaymentGateway::createRequest(external_id = "order_{id}_{attempt}", amount, due_at)
    ← Pairrot 201 / 200 replayed (idempotentní podle external_id)
    selhání → retry s backoffem; Pairrot stojí → objednávka zůstává, účastník
    má v samoobsluze „platební údaje dorazí e-mailem“                       PAY-03, S08
    úspěch → PaymentRequestIssued (VS, IBAN, SPAYD) → outbox
outbox relay → Communication: SendPaymentInstructions (idempotence per delivery)  S09
```

### B.4 Příchozí platba (S03, S04, S07, S05, S06)

```
POST /api/webhooks/pairrot
 └─ PairrotWebhookAuthenticator: HMAC-SHA256("<id>.<ts>." + body), ±5 min, constant time,
    podpora dvou klíčů při rotaci
 └─ INSERT INTO inbox_message(provider, event_id UNIQUE, payload) ON CONFLICT DO NOTHING → 200
    (zpracování je oddělené od přijetí; duplicitní doručení skončí na unique)              S03
 └─ async: ApplyPairrotPaymentUpdate
     - payment_request.last_sequence < data.sequence? jinak ignoruj (mimo pořadí)
     - delta = current.paid_amount - known_paid → LedgerEntry(pairrot_allocation | revocation)
     - FinancialState fold → partially_paid | paid | overpaid
     - outbox: OrderPaymentChanged{state, delta}
 └─ Registration subscriber (reaguje na finanční stav):
     paid      → účasti pending → confirmed (workflow), held → confirmed v bucketech
     partially → nic + plánovaná upomínka na doplatek                                        S04
     overpaid  → výjimka + refund úkol dle nastavení                                         S07
     platba na expirovanou účast:
        auto_restore && volná kapacita → re-claim bucketu → confirmed (actor=system)        S06
        jinak → výjimka „opožděná platba“ (+ refund úkol, je-li plno)                        S05
 └─ Scheduler: každých 15 min reconcile přes GET /v1/management/webhook-events (posledních 48 h)
```

### B.5 Ostatní mechanismy v B

| Oblast | Řešení |
|---|---|
| Expirace rezervací (CAP-04) | Scheduler každou minutu: `SELECT … WHERE state='pending' AND hold_expires_at < now() FOR UPDATE SKIP LOCKED LIMIT 200` → workflow `expire` → uvolnění bucketů → outbox `ParticipationExpired` → notifikace, čekací listina (P1). Idempotentní sweeper. |
| Odbavení (S10) | `check_in` + částečný unique index `WHERE valid`. Druhý insert spadne na unique a vrátí 409 s „odbaveno v HH:MM kým“. Oprava = zneplatnění + nový záznam v jedné transakci. |
| Cílené zprávy (S12, COM-02) | `ActivityRescheduled` → Communication spočítá příjemce dotazem nad Registration API (účasti dané aktivity ve stavu confirmed/pending). Každé doručení se před odesláním znovu ověří (`RecipientStillEligible`), jinak `skipped`. |
| Verze dokumentu s reconfirm (S15) | `DocumentVersionPublished{requiresReconfirmation}` → Legal najde dotčená potvrzení → účasti `requires_attention` (workflow) → výzvy. Staré potvrzení zůstává, nové přibude. |
| Změna varianty (S18) | Jeden příkaz v jedné transakci: claim nových bucketů, release starých, nová účast nebo změna varianty, přepočet `amountDue` → fold ledgeru dá nedoplatek nebo přeplatek → outbox. |
| GDPR výmaz (P1) | Anonymizace **in place** (UPDATE osobních sloupců a JSONB odpovědí). Audit osobní údaje nedrží (before/after obsahuje jen ne-osobní pole a reference, osobní diffy jsou v `audit_pii` s kratší retenční třídou). Ledger osobní údaje nemá. |
| Reporting | SQL pohledy a materializované pohledy nad stavovými tabulkami. Bez projekční infrastruktury. |

### B.6 Silné a slabé stránky B

**Silné**
- Atomicita kapacit je triviální: vše v jedné transakci, jedna SQL operace na bucket.
- Silná konzistence čtení: admin vidí, co zapsal. Žádný projection lag.
- GDPR výmaz je jednoduchý (anonymizace v řádku).
- Méně konceptů, rychlejší dodání P0 + P1, nižší riziko nedokončeného dema.
- Ledger plateb ukazuje event sourcing tam, kde dává největší smysl (peníze, kompenzace, odvozený stav).
- Showcase Symfony: vlastní kód drží DI kontejner, Workflow, Messenger a Scheduler. Nic z toho neschovává knihovna.

**Slabé**
- Historie mimo peníze je jen audit, ne přehratelný stav. „Jak vypadala objednávka v úterý v 14:00“ se skládá z auditu.
- Disciplína „každá změna = audit + outbox ve stejné transakci“ se musí hlídat (middleware + test, že každý command emituje událost).
- Doctrine entity s chováním vyžadují kázeň (žádné settery, mapping mimo doménu).

**Odhad:** základ (skelet, outbox, buses, tenancy, auth) zhruba 1,5 týdne, P0 další 4–5 týdnů, P1 2–3 týdny.

---

## Část II — Směr C: CQRS + event sourcing objednávky a účasti

### C.1 Co se mění

Agregáty `Order` (včetně osob, účastí a doplňků) a nově `CapacityPool` nemají tabulky se stavem. Mají **proudy událostí**:

```
Order-<uuid>:
  OrderPlaced{persons, participations[{id, activity, variant, wave, holdUntil}], items, formVersion, amountDue}
  LegalRequirementConfirmed{participationId, personId, requirementId, documentVersionId, decision, channel}
  PaymentRequestIssued{vs, iban, spayd, dueAt}
  PaymentReceived{source, amount, externalRef, sequence}
  ParticipationConfirmed{participationId, reason: paid|cash|free|manual}
  ParticipationExpired{participationId}
  ParticipationCancelled{participationId, by, reason}
  ParticipationVariantChanged{…}
  AmountDueChanged{old, new, reason}
  RefundTaskOpened{…}
  …
```

Stav agregátu vzniká přehráním (`apply*` metody). Zápis má podobu `append` s optimistickou kontrolou verze proudu. Čtení obstarávají **projekce**, asynchronní nebo synchronní subscribery nad event store, které plní read tabulky (seznam registrací, dostupnost, odbavovací seznamy, finanční přehled, přehled souhlasů).

Knihovna: **patchlevel/event-sourcing + bundle** (Symfony-native, Doctrine DBAL store, atributy `#[Aggregate]`, `#[Apply]`, `#[Projector]`, subscription engine, upcasting, podpora osobních údajů `#[PersonalData]` / `#[DataSubjectId]` s crypto-shreddingem). Alternativy: EventSauce (víc DIY), Ecotone (vlastní framework v rámci frameworku).

### C.2 Hlavní problém: kapacita napříč úrovněmi (S01, S02)

Event sourcing chrání invarianty **uvnitř jednoho proudu**. Kapacita je ale invariant **napříč mnoha objednávkami** a navíc na 2–4 úrovních najednou. Možnosti:

| Varianta | Jak | Cena |
|---|---|---|
| C-a Kapacitní agregát per bucket | `CapacityPool-<bucket>` jako vlastní proud. Objednávka musí appendnout do Order **i** do 2–4 poolů atomicky. S Doctrine store v Postgresu to jde v jedné DB transakci (multi-stream append). | „Nečisté“ ES (víc agregátů v jedné transakci). Každý pool je hot stream, takže při náporu přijdou konflikty verzí a retry. Stejný problém jako optimistické zamykání (osa 3c). |
| C-b Rezervační saga | Nejdřív `CapacityReserved` v poolech, pak `OrderPlaced`, při selhání kompenzace `CapacityReleased`. | Eventual consistency uvnitř registrace a kompenzační logika. S02 („nikdy částečně obsazené místo“) platí jen eventuálně. |
| C-c Hybrid | Kapacita zůstává stavová tabulka `capacity_bucket` (jako B) a ve **stejné DB transakci** se appenduje Order stream. | Funguje spolehlivě a rychle, ale kapacita není event-sourced. Prakticky se tím blíží B. |
| C-d Dynamic Consistency Boundary (DCB) | Novější přístup (2024/25): žádné agregáty, events s tagy (`activity:X`, `variant:Y`), append s podmínkou „od pozice P nepřibyla událost s tagy T“. Řeší přesně invarianty napříč entitami. | V PHP zatím jen experimentální knihovny. Vysoké riziko a pro pohovor spíš téma k diskusi než k produkci. |

Realisticky by C skončilo u **C-c**: ES pro Order, kapacita stavová.

### C.3 Zápisová cesta: registrace v C (s C-c)

```
PlaceOrderHandler (jedna DB transakce)
  1–4 jako v B (snapshot katalogu, validace formuláře, cena, právní požadavky)
  5   CapacityAllocator nad capacity_bucket (stejné SQL jako B)
  6   Order::place(...) → zaznamenané události
  7   repository->save($order) → INSERT do event store (stream Order-<id>, playhead 1..n)
  8   synchronní projekce (inline): order_list_row, participation_row (kvůli read-your-writes v adminu)
COMMIT
→ asynchronní projekce: availability, finance_overview, consent_overview, check_in_list
→ subscribery (procesní manažeři): CreatePaymentRequest, SendConfirmation
```

### C.4 Další dopady C

| Oblast | Dopad |
|---|---|
| Audit (AUD) | Proud událostí **je** audit pro Order. Metadata každé události musí nést actor, reason a correlation (AUD-02). Pro moduly mimo ES (Catalog, Legal dokumenty, Identity) je audit log potřeba stejně, takže vzniknou dva auditní mechanismy. |
| Odvozený finanční stav | Přirozený: `PaymentReceived` a `AmountDueChanged` v proudu, fold v agregátu. |
| GDPR výmaz (PRV-04) | Neměnné události obsahují jména, data narození a odpovědi formuláře. Řešením je **crypto-shredding**: osobní údaje v událostech šifrované klíčem per osoba, výmaz = smazání klíče. Patchlevel to podporuje, ale projekce musí umět „osoba vymazána“ a export (PRV-02) musí dešifrovat. Odpovědi formuláře (JSONB, libovolná pole) se musí šifrovat celé. |
| Verzování schémat událostí | Každá změna tvaru události znamená upcaster. Během vývoje P0 → P1 se události budou měnit často. |
| Projection lag | Veřejná dostupnost a admin seznamy jsou eventuálně konzistentní (ms až s). Read-your-writes v adminu vyžaduje inline projekce nebo čekání na pozici. |
| Opravy dat | Žádný `UPDATE`. Každá oprava je kompenzační událost, chybná událost zůstává navždy. Pro doménu je to správně, pro vývoj pomalejší. |
| Rebuild | Projekce lze zahodit a přehrát. Silný argument pro nové reporty (ADM-08) i pro P2 porovnání ročníků. |
| Testy | Given/When/Then nad agregátem (`given(OrderPlaced…)->when(pay…)->then(ParticipationConfirmed…)`). Velmi čitelné a scénáře S03–S07, S18 se tak dají vyjádřit elegantně. |
| Temporal queries | „Stav objednávky k datu X“ zdarma (přehrání do pozice). |

### C.5 Silné a slabé stránky C

**Silné**
- Úplná historie a přehratelnost Order a účastí. Audit je vedlejší produkt.
- Elegantní doménové testy. Silná vypovídací hodnota u pohovoru („umím ES a vím, kde bolí“).
- Read modely optimalizované pro každou obrazovku (odbavení, finance, souhlasy) a přestavitelné.

**Slabé**
- Kapacita, nejdůležitější invariant zadání, se do ES nevejde čistě a skončí hybridem (C-c).
- GDPR: crypto-shredding a šifrované JSONB odpovědi jsou netriviální a v doméně, kde je GDPR „páteřní feature dema“, zvyšují riziko.
- Dva auditní mechanismy (ES streamy vs. audit log pro zbytek).
- Projection lag a k tomu inline projekce, subscription engine, správa pozic a rebuildů.
- Knihovna (Patchlevel) dělá velkou část DI a konvencí za nás, takže zůstává méně vlastního kódu ukazujícího Symfony kontejner.
- Pomalejší vývoj kvůli upcasterům a kompenzačním událostem i při ladění.

**Odhad:** základ zhruba 2,5 týdne, P0 6–8 týdnů, P1 3–4 týdny. Riziko nedotaženého dema je vysoké.

---

## Část III — Srovnání

| Kritérium | B (stav + ledger ES) | C (ES Order + projekce) |
|---|---|---|
| Atomická kapacita S01/S02 | nativně, jedna transakce | hybrid (C-c) nebo saga |
| Idempotence S03 | inbox unique + sequence | totéž + playhead |
| Odvozené finance S04/S07/S11 | fold ledgeru | fold proudu |
| Pozdní platba S05/S06 | workflow + subscriber | procesní manažer |
| Souběh odbavení S10 | partial unique index | zvláštní agregát nebo unique v projekci (+ složitost) |
| Verzování formuláře/dokumentů S14/S15/S17 | snapshoty (stejné v obou) | snapshoty (stejné v obou) |
| Audit AUD-01–04 | jeden mechanismus (audit log + ledger) | streamy + audit log pro zbytek |
| GDPR výmaz | anonymizace v řádku | crypto-shredding |
| Konzistence čtení | silná | eventuální (inline projekce kde nutné) |
| Historie „k datu“ | jen přes audit | nativní |
| Testy domény | handler + DB integrační | Given/When/Then nad agregátem + integrační |
| Showcase Symfony DI | vysoký (vše vlastní) | střední (část dělá knihovna) |
| Showcase architektury | vysoký: modulární monolit, outbox, ledger, vědomé ADR „proč ne ES všude“ | velmi vysoký: ES/CQRS, ale s viditelnými kompromisy |
| Čas P0 + P1 | ~8–10 týdnů | ~12–14 týdnů |
| Riziko | nízké–střední | vysoké |

## Část IV — Doporučení

**B + event-sourced ledger.** Ledger plateb je přesně ta část domény, kde ES přináší hodnotu (neměnnost, kompenzace, odvozený stav, idempotence), a kde naopak nebolí (ledger nemá osobní údaje ani invarianty napříč agregáty).

Pro pohovor je silnější argumentovat **proč ES jen tam** (ADR s rozborem C-a až C-d výše, včetně DCB) a mít funkční, dotažený produkt, než mít ES všude a nedodělanou kapacitu nebo GDPR.

Kdyby přesto byl zájem o víc ES, existuje středová varianta **B+ (Order-ES lite)**. Agregát Order se ukládá do Patchlevel streamu, kapacita zůstává stavová (C-c) a GDPR se řeší tím, že osobní údaje a odpovědi formuláře **nejsou v událostech** (události nesou jen `personId` a odkaz na `answer_set`, osobní data žijí ve stavové tabulce `person` s anonymizací in place). Události pak zůstávají neměnné bez crypto-shreddingu. Cena: zhruba +2–3 týdny proti B a dva auditní mechanismy.
