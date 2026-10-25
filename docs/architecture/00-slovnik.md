[Architektura](README.md) · **00 Slovník** · [01 Kontext a nasazení →](01-kontext-a-nasazeni/README.md)

# 00 · Slovník

Zadání má závazný český slovník (kap. 2). Kód je anglicky. Tato tabulka je jediné místo, kde se obě jména potkávají, a kniha i kód se jí drží. Jméno v kódu je jméno třídy nebo agregátu. Tabulka v DB je jeho `snake_case` podoba.

## Doménové pojmy

| Pojem (zadání) | Kód | Modul | Poznámka |
|---|---|---|---|
| Organizátor (tenant) | `Organizer` | Identity | tenant; `organizer_id` je na každé tenant-scoped tabulce |
| Člen týmu | `Membership` (účet × organizátor × role × [události]) | Identity | účet je `UserAccount`, jeden účet může mít více membershipů |
| Role | `OrganizerRole`: `OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN` | Identity | |
| Událost | `Event` | Catalog | veřejný odkaz `/{orgSlug}/{eventSlug}` |
| Aktivita | `Activity` | Catalog | jednoduchá událost má právě jednu (`implicit = true`) |
| Varianta | `Variant` | Catalog | `seats` = počet osob (1 jednotlivec, N tým) |
| Vlna (slot) | `Wave` | Catalog | P1 |
| Kategorie | `CategoryRule` → `Category` | Catalog | výsledek pravidla, ne volba účastníka |
| Programový bod | `ProgramItem` | Catalog | volitelná vazba na `Activity` |
| Aktualita | `NewsPost` | Catalog | |
| Doplňková položka | `AddOn` (katalog) / `OrderItem` (v objednávce) | Catalog / Registration | |
| Verze formuláře | `FormVersion` (neměnný JSONB snapshot `FormSchema`) | Catalog | |
| Pole formuláře | `FieldDefinition` + `FieldType` (tagovaná služba) | Catalog | scope: `ORDER`, `PERSON`, `PARTICIPATION`, `GROUP` |
| Objednávka | `Order` | Registration | agregát; drží osoby, účasti, položky, skupiny |
| Objednávající | `Orderer` (embeddable kontakt na `Order`) | Registration | nemusí být účastník |
| Osoba | `Person` | Registration | sémantická pole jsou typované sloupce |
| Účast | `Participation` | Registration | nositel kapacity, QR, odbavení a potvrzení vázaných na aktivitu |
| Skupina / tým | `Group` | Registration | |
| Odpovědi | `AnswerSet` (scope + `form_version_id` + JSONB) | Registration | |
| Rezervace místa | `CapacityClaim` + `Participation.holdExpiresAt` | Registration | hold není stav, je to expirace na čekající účasti |
| Kapacitní úroveň | `CapacityBucket` | Registration | jeden řádek pro událost, aktivitu, variantu a vlnu |
| Čekací listina | `WaitlistEntry`, `WaitlistOffer` | Registration | P1 |
| Sleva / promo kód | `Discount`, `PromoCode`, `PriceModifier` (tagovaná služba) | Registration | P1 |
| Token samoobsluhy | `SelfServiceToken` | Registration | |
| Platební požadavek | `PaymentRequest` | Payments | 1:1 s požadavkem v Pairrotu (`external_id = order_<uuid>_<attempt>`) |
| Platební událost | `LedgerEntry` | Payments | append-only; druhy viz kap. 05 |
| Finanční stav | `FinancialState` (fold ledgeru) | Payments | cache na `order_finance` |
| Refund úkol | `RefundTask` | Payments | vrácení peněz probíhá ručně mimo systém |
| Nespárovaná platba | `UnmatchedTransaction` (zrcadlo transakce Pairrotu) | Payments | rekonciliační fronta |
| Právní dokument | `LegalDocument` + `DocumentVersion` | Legal | úroveň: `ORGANIZER`, `EVENT`, `ACTIVITY` |
| Právní požadavek | `LegalRequirement` | Legal | typ: `INFORMATION`, `TERMS`, `CONSENT`, `LIKENESS`, `DECLARATION` |
| Potvrzení | `Confirmation` | Legal | append-only; vazba na verzi, osobu a účast/objednávku |
| Odbavovací seznam | `CheckInList` | CheckIn | výchozí: 1 = aktivita |
| Stanoviště | `CheckInStation` | CheckIn | P1 (více stanovišť) |
| Odbavení | `CheckIn` | CheckIn | právě jeden platný záznam na účast |
| QR token účasti | `ParticipationQrToken` | CheckIn | podepsaný, verzovaný |
| Zpráva / kampaň | `Campaign` | Communication | |
| Šablona | `MessageTemplate` | Communication | |
| Doručení | `Delivery` | Communication | jedno na příjemce |
| Galerie / kolekce | `Collection` (`INTERNAL` / `EXTERNAL`) | Content | |
| Fotografie | `Photo` | Content | |
| Materiál | `Material` | Content | |
| Výjimka | `AttentionItem` | Attention | fronta „vyžaduje pozornost“ |
| Auditní záznam | `AuditEntry` | Audit | append-only |
| Retenční třída | `RetentionClass` | Privacy | `REGISTRATION`, `FINANCIAL`, `CONFIRMATION`, `MEDIA` |
| Žádost subjektu | `DataSubjectRequest` | Privacy | P1 |

## Technické pojmy

| Pojem | Význam |
|---|---|
| **Outbox** | tabulka `messenger_messages` Doctrine transportu Messengeru. Integrační událost se do ní zapíše ve stejné DB transakci jako změna stavu, jeden řádek na každý transport, kam je událost routovaná ([06](06-asynchronni-svet/README.md), [ADR 003](../adr/003-transactional-outbox-per-subscriber-delivery.md)). |
| **Inbox** | `inbox_message`, přijaté webhooky s unikátním ID odesílatele. Zajišťuje deduplikaci, zpracování pak běží přes Messenger. |
| **Odběratel (handler)** | `#[AsMessageHandler(fromTransport: '…')]` v modulu, který na integrační událost reaguje. Každý odběratel má vlastní transport, takže i vlastní retry a failure transport. |
| **Command / use case** | zpráva pro `command.bus` (mění stav, jedna transakce) a její handler. Leží spolu ve složce use casu `Application/<UseCase>/`. Čtení nejde přes bus, ale přes query službu. |
| **Doménová událost** | interní fakt agregátu (`Domain/Event`), zpracuje se synchronně uvnitř modulu |
| **Integrační událost** | veřejný, verzovaný fakt bez osobních údajů v `Contract/Event/` (`OrderPlaced`), který posílá modul ostatním přes `event.bus` ([katalog](06-asynchronni-svet/katalog-udalosti.md)) |
| **Fold** | stav vypočtený přehráním append-only záznamů, například `FinancialState` z `LedgerEntry` |
| **Minor units** | peníze v haléřích jako `int`, nikdy `float` |
| **Kontrakt modulu** | `src/<Modul>/Contract/` (fasády, DTO, integrační události, výjimky), jediný jmenný prostor, který smí importovat jiný modul. Nezaměňovat s HTTP API. |
| **Tenant scope** | Doctrine filtr `organizer`, který omezí všechny dotazy na aktuálního organizátora |
| **Idempotenční záznam** | `idempotency_record`: klíč z hlavičky `Idempotency-Key`, hash kanonického těla a uložená odpověď. Stejné tělo vrátí uloženou odpověď, jiné tělo skončí `409`. |
| **Finanční cache** | `order_finance`: odvozený finanční stav objednávky (`state`, `paid_minor`, `due_minor`, `ledger_version`), přepočtený ve stejné transakci jako zápis do ledgeru |
| **Stav synchronizace s Pairrotem** | `payment_request.known_paid_amount` a `payment_request.last_sequence`: z nich se počítá delta platby a podle nich se zahazují webhooky doručené mimo pořadí |
| **Append-only tabulky** | `ledger_entry`, `audit_entry` a `confirmation` aplikace jen přidává. U `check_in` smí aplikace změnit jen sloupce zneplatnění (`voided_at`, `voided_by`, `void_reason`). |
| **DB role** | `eventor_owner` spouští migrace, `eventor_app` je běh aplikace s omezenými granty |
| **`scheduled_run`** | záznam každého běhu úlohy Scheduleru (začátek, konec, výsledek, počet zpracovaných položek). Selhaný běh se promítne do fronty výjimek. |
| **`audit_pii`** | osobní údaje z auditních diffů; mají vlastní, kratší retenční třídu |
| **`#[ReadOnlyCommand]`** | označí command, který záměrně neemituje doménovou událost. Test vyžaduje, aby každý jiný command nějakou emitoval. |
| **Zrcadlo Pairrotu** | lokální kopie stavu požadavků a transakcí z Pairrotu (`payment_request`, `unmatched_transaction`). Zdroj pravdy zůstává v Pairrotu. |

## Prefixy a identifikátory

| Identifikátor | Tvar | Kde |
|---|---|---|
| Interní ID | UUIDv7 (`symfony/uid`) | všechny entity |
| Registrační kód | 8 znaků Crockford Base32 (`K7M2-QX9D`) | účast; vyhledání při odbavení (CHK-02) |
| Číslo objednávky | `EV-2026-000123` | objednávka; komunikace s účastníkem |
| Pairrot subtenant | `eventor_org_<uuid>` | 1:1 s organizátorem |
| Pairrot `external_id` | `order_<uuid>_<attempt>` | platební požadavek |
| Token samoobsluhy | `ss1_<id>_<secret>` | odkaz v e-mailu, v DB jen hash. Frontend ho z odkazu přenese do hlavičky `Authorization: SelfService …`. |
| QR token účasti | `qr1.<payload>.<sig>` | QR kód účasti |
| Token zařízení | `dev1_<id>_<secret>` | odbavovací zařízení (`device_token`) |
| Veřejná URL události | `/{orgSlug}/{eventSlug}` (Next.js) → API `/api/public/orgs/{orgSlug}/events/{eventSlug}` | krátkou URL vlastní frontend |
