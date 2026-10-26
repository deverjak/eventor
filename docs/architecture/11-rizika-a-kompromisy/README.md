[Architektura](../README.md) · [← 10 Kvalita a testování](../10-kvalita-a-testovani/README.md) · **11 Rizika a kompromisy** · [12 Plán implementace →](../12-plan-implementace/README.md)

# 11 · Rizika a kompromisy

## Co si odnést

Každá architektura je soubor vědomých kompromisů. Eventor jich dělá osm a u každého ví, co za něj platí a kdy by ho přehodnotil. Největší rizika nejsou technická: je to **rozsah P0 + P1 vůči času** (R1) a **odchylka `pairrot-sandbox` od skutečného Pairrotu** (R2). Obě mají plán hned od fáze F0. Kapitola zároveň slouží jako místo, kam se zapisují rozpory mezi knihou a kódem, jakmile kód vznikne. Poslední část shrnuje, co si Eventor vzal z bolestí main-panelu.

![Rizika podle pravděpodobnosti a dopadu](img/risk-heatmap.png)

**Jak to číst.** Vodorovně je pravděpodobnost, svisle dopad na demo a produkt. Pravý horní kvadrant znamená řešit hned. Oranžová rizika mají zmírnění naplánované od F0, ostatní v té fázi, kde vznikají ([12](../12-plan-implementace/README.md)). Pozice jsou odhad architekta k 24. 10. 2026 a mění se s každou dokončenou fází.

## Vědomé kompromisy

| # | Kompromis | Co tím Eventor získá | Co za to platí | Kdy přehodnotit |
|---|---|---|---|---|
| K1 | **Žádný event sourcing objednávky a účasti**, jen ledger plateb ([ADR 001](../../adr/001-modular-monolith-with-event-sourced-ledger.md)) | atomická kapacita v jedné transakci, jednoduchý GDPR výmaz, silná konzistence čtení | stav objednávky k libovolnému datu se skládá z auditu, ne přehráním | až budou potřeba temporal queries nad objednávkou (P2 porovnání ročníků); cesta B+ je popsaná v [navrhy/01](../navrhy/01-smer-b-vs-c.md) |
| K2 | **Jeden Postgres pro vše** včetně outboxu a front ([ADR 009](../../adr/009-postgresql.md)) | žádný broker, outbox ve stejné transakci, jednodušší provoz a zálohy | DB je single point of failure; worker a web soutěží o spojení | nárazové otevření registrací přes ~500 souběžných zápisů, nebo potřeba nezávislého škálování workerů → Redis/RabbitMQ transport (doména se nemění) |
| K3 | **Worker ve stejném image** jako web (FrankenPHP; `messenger:consume outbox async scheduler_default`) | jedna build pipeline, stejný kód a konfigurace | dlouhý požadavek nemůže zablokovat worker, ale nasazení restartuje oba | při nasazení na více strojů → samostatný deployment workeru (image zůstává) |
| K4 | **Pairrot dnes umí jen Českou spořitelnu a CZK** | žádné vlastní bankovní párování (zadání: mimo rozsah) | organizátor s jinou bankou má jen ruční evidenci plateb; demo musí použít ČS profil | až Pairrot přidá banky (bez změny Eventoru, jde o konfiguraci bank connection) |
| K5 | **Retry budget webhooků Pairrotu je asi hodina** | Eventor se nespoléhá jen na push | potřeba reconcile jobu (každých 15 min, okno 48 h) a zrcadla `payment_request.last_sequence` | nikdy úplně; reconcile zůstane jako pojistka i při delším retry |
| K6 | **Refundace jsou ruční** (`RefundTask`, PAY-11) | žádná platební brána, žádné bankovní API | finance musí peníze vrátit mimo systém a úkol uzavřít ručně | P2+ s platební bránou nebo bankovním API |
| K7 | **Odbavení je jen online** (CHK-12 je P2) | žádná synchronizace a konflikty | výpadek sítě na místě zastaví odbavení | P2: QR token je podepsaný a verzovaný už teď, takže offline ověření podpisu jde doplnit bez změny formátu |
| K8 | **Kapacita jako denormalizované countery** (`capacity_bucket.held/confirmed`) místo počítání účastí ([ADR 002](../../adr/002-capacity-buckets-conditional-update.md)) | jedna podmíněná operace na úroveň, krátké zámky, S01/S02 v jedné transakci | counter může teoreticky ujet od skutečnosti (chyba v kódu, ruční zásah v DB) | nikdy; drift hlídá `VerifyCapacityCounters` (noční dotaz součet claimů vs. bucket → `AttentionItem`) a scénářové testy |

## Rizika

| # | Riziko | Pravd. | Dopad | Zmírnění | Fáze |
|---|---|:-:|:-:|---|---|
| R1 | **Rozsah P0 + P1 se nevejde do 8–10 týdnů** | vysoká | vysoký | P0 je hotové v M2 (T7) dřív, než začne P1. P1 je seřazené podle hodnoty pro demo: čekací listina → slevy → kategorie a vlny → převod → PRV → týmová varianta. Co se nevejde, zůstane navržené (architektura už počítá s místem). Každá fáze končí zelenými scénáři, ne „skoro hotovo“. | F0–F8 |
| R2 | **`pairrot-sandbox` se chová jinak než skutečný Pairrot** | střední | vysoký | Kontrakt se píše podle skutečného Pairrotu (README, `webhooks-receiver.md`, testovací vektor podpisu). Kontraktní testy běží proti sandboxu i proti vektorům z dokumentace. Seznam otevřených otázek (níže) se uzavře v F3. Adaptér je jediné místo, které zná tvar API. | F0, F3 |
| R3 | **Editor formuláře je pracnější, než se zdá** (FRM-01–11, podmínky, scope, náhled) | vysoká | střední | Server je jediný zdroj pravdy (schéma + validace). Frontend editor začne jako formulář nad JSON schématem s náhledem a drag & drop až nakonec. Typ pole se přidává jako tagovaná služba, takže jádro se nemění. | F1 |
| R4 | **Webhooky z Pairrotu nedorazí déle než hodinu** (výpadek Eventoru, síť) | střední | střední | reconcile každých 15 min přes event log (48 h okno), idempotentní aplikace (inbox + `sequence`); delší výpadek → ruční `eventor:pairrot:reconcile --since=` | F3 |
| R5 | **Drift kapacitních counterů** | nízká | vysoký | CHECK constraint, jediná cesta zápisu přes `CapacityAllocator`, noční `VerifyCapacityCounters`, scénáře S01/S02/S05/S06/S18 | F2 |
| R6 | **Neúplná anonymizace** (osobní údaj zůstane v JSONB odpovědích, logu nebo auditu) | nízká–střední | vysoký | osobní údaje jen v `person` a `answer_set`, audit drží osobní diffy v `audit_pii` s vlastní retencí, PHPStan `NoPersonalDataInEventRule`, Monolog procesor redakce, test „po anonymizaci nenajdeš e-mail v žádné tabulce“ | F7 |
| R7 | **Jedovatá zpráva v outboxu** (odběratel padá pořád) | nízká | střední | per-subscriber delivery: padá jen jeden odběratel, ostatní pokračují; po 6. pokusu `dead` → `AttentionItem(dead_letter)` s akcí replay | F0 |
| R8 | **Sdílený stav v worker módu FrankenPHP** (služba drží stav mezi požadavky: tenant, Doctrine filtr) | střední | nízký–střední | `TenantContext` implementuje `ResetInterface`, `kernel.reset` mezi požadavky, test „dva požadavky dvou tenantů za sebou v jednom procesu“; EM se resetuje | F0 |
| R9 | **Pairrot jen Česká spořitelna** | vysoká | nízký | pro demo stačí; organizátor bez ČS má hotovost a ruční evidenci (K4) | — |

## Otevřené otázky k ověření proti `pairrot-sandbox` (uzavřít v F3)

1. Simuluje sandbox i **manuální alokaci** (částečná úhrada S04, přeplatek S07) a události `transaction.recorded` s `matching.result`, nebo jen přesně spárované platby?
2. Odpovídá **tvar webhooku** včetně `previous`/`current` a monotónní `sequence` a posílá sandbox i **mimo pořadí a duplicitně** (S03), jak to dovoluje kontrakt?
3. Podporuje sandbox **rotaci webhook klíče** (dva podpisy v hlavičce)?
4. Je k dispozici **event log** `GET /v1/management/webhook-events` s kurzorem pro reconcile?
5. Vrací sandbox `200 replayed` / `409 external_id_conflict` podle skutečného chování idempotence?
6. Jak sandbox reprezentuje **subtenanty a bank connections** (provisioning organizátora v ORG-02)?
7. Jaký je chování **`allocation_revoked`**? Eventor z něj tvoří kompenzační `ledger_entry` se zápornou deltou.
8. Je možné simulovat **výpadek** (503, timeout) pro S08 a retry dekorátor?

Odpovědi se zapíší do [07 · Integrace](../07-integrace/README.md) a [ADR 004](../../adr/004-pairrot-integration-contract.md). Co sandbox neumí, se pokryje `InMemoryPaymentGateway` ve scénářových testech a v sandboxu se označí jako mezera.

## Co si Eventor vzal z bolestí main-panelu

Main-panel (NaLekci) prošel cestou k modulárnímu monolitu zpětně. Jeho kapitola „Kde to bolí“ je pro Eventor kontrolní seznam toho, co nedělat.

| Bolest v main-panelu | Odpověď Eventoru |
|---|---|
| privilegovaná operace zapisovala částky tak, jak je poslal volající | server vždy přepočítá cenu i finanční stav; částky z klienta se jen porovnají (rekapitulace REG-05 je výsledek serveru) |
| „append-only“ tabulky šly mazat (`DELETE`, `TRUNCATE`) | append-only se vynucuje granty DB role, ne triggery, a hlídá ho migrační test |
| kaskády mazaly finanční historii | `RESTRICT` na všech cizích klíčích do ledgeru, plateb a auditu; objednávka se nikdy nemaže, jen anonymizuje |
| refund mohl spustit kdokoli přihlášený | matice oprávnění ze zadání (kap. 3.2) jako `PermissionMatrix` + voters, ověřená compiler passem |
| cykly mezi moduly, které nikdo nehlídal | deptrac bez baseline a `ModuleBoundaryPass` od prvního commitu |
| stejné pravidlo v SQL i v aplikaci, které se rozjelo | pravidla jsou jen v PHP doméně; DB drží jen invarianty (CHECK, unique), ne logiku |
| „upovídané“ zápisové cesty s desítkami round-tripů | jeden command, jedna transakce, jedna podmíněná SQL operace na kapacitní úroveň |
| e-maily posílané mimo outbox se ztrácely | e-mail odchází výhradně jako odběratel outboxu (COM-07, S09) |
| crony bez zpětné vazby | Scheduler zapisuje `scheduled_run`, neúspěch tvoří `AttentionItem` |
| manifest událostí neodpovídal realitě | katalog událostí ověřuje `IntegrationEventRoutingPass` při kompilaci |
| tajemství porovnávané `===`, fallback na jiný klíč | `hash_equals` všude, žádné fallbacky klíčů, tajemství ve Symfony Secrets |
| dokumentace se rozcházela s kódem | definice hotového zahrnuje knihu; workflow dump a check-links v CI |

## Odchylky implementace od návrhu (W0–W4)

Platí stav ověřený ve zdrojovém kódu 25. 10. 2026; „důvod“ popisuje implementační kompromis, nikoli nové ADR rozhodnutí.

| Vlna | Kniha původně říkala | Implementace | Důvod |
|---|---|---|---|
| W0 | YAML konfigurace | PHP konfigurace `App::config([...])` | skutečně použitý Symfony styl konfigurace |
| W0 | standardní runtime | vlastní `FrankenPhpRuntime` a worker runner | přizpůsobení FrankenPHP worker režimu |
| W0 | pass rozpozná reálnou službu | `ModuleBoundaryPass` soudí podle deklarovaného typu parametru konstruktoru | omezení současné implementace passu |
| W0 | inlinovaná cizí interní služba nevadí | pass ji nesprávně přičte konzumentovi; obchází se dvěma veřejnými službami | oprava passu čeká |
| W0 | libovolný modul může injektovat cizí implementaci Shared portu | neinjektuje ji; Payments hlásí anomálie událostí místo `AttentionRaiser` | omezení hranice DI |
| W0 | neurčená tabulka/odpověď idempotence | `shared.idempotency_record`, dokončená odpověď jako JSON | DBAL implementace store |
| W0 | problem detail s neurčeným dalším krokem | `nextSteps` je seznam akcí | serializační kontrakt API |
| W0 | Money bez prezentační hodnoty | `{minor, currency, formatted}` | pohodlí klienta; vstup `formatted` ignoruje |
| W0 | běžné CSRF tokeny | hlavička `csrf-token` s libovolnou hodnotou délky alespoň 24 a stejný `Origin` | ochrana pro same-origin API |
| W0 | outbox probouzí `LISTEN/NOTIFY` | `use_notify` se nepoužívá | worker polluje Doctrine transport |
| W0 | neurčená cesta zápisu | zápisy vedou přes port `CommandBus`; runtime ověřuje událost z každého commandu | vynucení aplikačního pravidla |
| W0 | audit a attention zapisují ORM cestou | zapisují přes DBAL porty | append-only a modulové rozhraní |
| W0 | neurčené umístění tenant kontextu | `Shared\Application\TenantContext` | skutečné členění Shared |
| W0 | `minio/minio` | `bitnamilegacy/minio:latest` | image v compose |
| W1 | Domain nesmí do vlastního Contract | deptrac povoluje Domain → vlastní Contract | doména používá vlastní výjimky a enumy |
| W1 | obálka události byla nejednotná | každá má `organizerId` první a `occurredAt` poslední | jednotný kontrakt integrací |
| W1 | Catalog vlastní/provisionuje kapacitu | buckety vlastní Registration; Catalog volá `CapacityProvisioner` | vlastnictví kapacity zůstává u registrace |
| W1 | finance objednávky vznikají asynchronně | `OrderFinanceInitializer` je synchronní v `PlaceOrder` | okamžitý finanční stav; rozpor kap. 02/04 vyřešen kódem |
| W1 | chyběl odběratel publikace události | `checkin` subscribuje `catalog.event_published` | založení výchozích check-in seznamů |
| W1 | chyběla fasáda onboarding | `Payments\Contract\PairrotOnboarding` | Identity vede průvodce, Payments vlastní Pairrot |
| W1 | self-service URL potřebuje uložené tajemství | `SelfServiceLinks::urlFor()` odvozuje token | tajemství necestuje po event busu |
| W1 | FRM-11 je Catalog | náhled formuláře je Registration | Registration už spojuje Catalog a Legal |
| W2 Catalog | venue je samostatná tabulka | `event.venues` je JSONB | jednodušší aktuální model |
| W2 Catalog | draft formuláře měl vlastní model | draft je `form_version` s `published_at IS NULL` | jedna tabulka verzí |
| W2 Catalog | entity bez veřejných setterů | settery jsou veřejné | vyžaduje je workflow marking store |
| W2 Catalog | chyběl veřejný detail dostupnosti | existuje `GET …/availability` | veřejná registrace |
| W2 Catalog | změna registrace jen pro událost | `catalog.registration_state_changed` vzniká i pro aktivitu | samostatná registrační okna |
| W2 Catalog | admin POST vyžadují idempotency key | nevyžadují jej | současný HTTP kontrakt |
| W2 Catalog | nula je platné maximum | `maxLength 0` a `maxChoices 0` se validují jako 1 | omezení validátoru |
| W2 Catalog | unlimited scope nemá bucket | provisionují se buckety všech scope | jednotné čtení a budoucí změna limitu |
| W2 Catalog | plánovač automaticky otevírá/zavírá registraci | dosud není | neimplementovaná automatizace |
| W2 Registration | data bucketu nemají `event_id`, hodnoty jsou velké | `capacity_bucket.event_id`; scope, unit a status jsou lowercase | skutečné DB schema |
| W2 Registration | uvolnění oznamuje následný subscriber | oznamuje je přímo `CapacityReleaser` | jedna odpovědnost za release |
| W2 Registration | přechod na unlimited neuvolňuje místa | oznámí `seats = 1` | invalidace dostupnosti |
| W2 Registration | availability cache neurčena | produkce: Postgres cache, TTL 30 s a tagy | dostupný cache backend |
| W2 Registration | `CapacityInvariantViolated` je problem výjimka | obyčejná výjimka | interní invariant |
| W2 Legal | hash a key vznikají při publikaci | vznikají už při vytvoření draftu | draft je pevně adresovatelný |
| W2 Legal | voluntary pravidlo jen pro consent | platí pro `consent` i `likeness` | oba typy jsou dobrovolné |
| W2 Legal | `preChecked` obecně | existuje jen pro rejection | právní pravidlo UI |
| W2 Legal | vlastní legal storage | media bucket s prefixem `legal/` | sdílený Flysystem bucket |
| W2 Legal | S14 neurčeno | nová verze bez reconfirmation nemění staré registrace | scénář S14 |
| W2 Legal | minor se určuje věkem | vychází z guardian řádků; `RegistrationSlot.minor` | dostupná data registrace |
| W2 Identity | invitation token uložený přímo | token HMAC odvozený, v DB jen SHA-256 | neukládat přijímací tajemství |
| W2 Identity | `acceptToken` vždy ve výstupu | jen dev a test | nevyzradit token v produkci |
| W2 Identity | check-in firewall obecný | request matcher: `Bearer dev1_` i cesta; jinak team firewall | správné rozlišení zařízení |
| W2 Identity | event scope z tokenu | `AccessPolicy::isAllowedForEvent`/`eventScope` čte DB | aktuální oprávnění |
| W2 Identity | opakované revoke je idempotentní | vrací 404 | stav již neexistuje |
| W2 Identity | oddělené limity registrace/pozvánky | sdílený limit 20/h v prod | ochrana jednoho vstupu |
| W2 Payments | priority dekorátorů obráceně | retry 30 (uvnitř), breaker 20, logging 10 | pořadí vrstev zůstalo správné |
| W2 Payments | anomálie přes attention port | `payments.integration_degraded` s kódem `reason` | omezení DI Shared portu |
| W2 Payments | `pairrot_revocation.compensates_id` povinné | je volitelné | webhook může nemít kompenzovanou položku |
| W2 Payments | jiný klíč ledger idempotence | `UNIQUE (organizer_id, source, source_ref)` | přirozený zdrojový klíč |
| W2 Payments | supplementary request z celé potřeby | `need − outstanding` částečně zaplacených requestů | nezduplikovat dlužnou částku |
| W2 Payments | neurčené review case | `PaymentDisputeOpened.reviewCaseId = event:<pairrot event id>` | dohledatelnost Pairrot události |
| W2 Payments | webhook secrets s povinným ID | seznam, `keyid:` je volitelný prefix | rotace i lokální klíče |
| W2 Payments | jiný prefix subtenantu | mimo prod `test_` | oddělení sandboxu |
| W2 Payments | cash permission ve voteru | kontroluje ji `CashDesk` | ochrana zápisové fasády |
| W2 Payments | reconcile mimo command transakci | HTTP běží uvnitř command transakce | současná implementace |
| W2 Payments | denní limit reconcile | není implementován | odložené omezení |
| W2 Payments | `PaymentRequest` má workflow | workflow nemá | stav je řízen adapterem/handlery |
| W2 Audit/Attention | event manager vidí jen vlastní akce | `AuditVoter` rozhoduje podle role; audit nemá event id | nelze bezpečně zúžit scope |
| W2 Audit/Attention | `assigned` | lowercase `open → acknowledged → resolved \| dismissed` | `assigned` přejmenováno na `acknowledged` |
| W2 Audit/Attention | obecná deduplikace | nejvýše jedna nevyřešená položka na `(type, subject)` | současný unique invariant |
| W2 Audit/Attention | všechny typy existují | chybějící se mapují na nejbližší typ a payload má `source` | postupná implementace typů |
| W2 Audit/Attention | akce měnící cizí modul jsou commandy | zatím jsou odkazy | zachování hranic modulů |
| W2 Audit/Attention | per-entity audit bez view/voteru | endpoint deklaruje `OrganizerView`, přístup ověřuje voter | typovaný pohled a autorizace |
| W2 (review) | scope členství na akce se kontroluje u každé akce | `RequiresPermissionListener` hlasuje nad `{eventId}` z routy (`EventPermissionSubject`); routa bez kontextu akce je členovi omezenému na akce odepřena, pokud nedeklaruje `scopedAllowed` (seznam akcí se mu filtruje) | jedno místo pro všechny moduly; generický `EventScopeTest` hlídá každou admin GET routu |
| W2 (review) | — | idempotenční záznam `in_progress` se po 24 h TTL smí převzít | přijaté riziko: požadavek nemůže běžet 24 h (limity PHP/FrankenPHP); trvalé zamčení klíče po pádu procesu by bylo horší |
| W2 (review) | veřejná stránka každé nekoncepční akce | detail jen pro `published`, `completed`, `cancelled`; formulář a dostupnost jen pro `published`; archiv je samostatná projekce (POST-07) | zrušení musí zůstat vidět účastníkům, osobní a provozní data archivu ne |
| W3 (review) | e-mail odejde právě jednou | doručení je *aspoň jednou*: SMTP odeslání proběhne před uložením `sent`; pád mezi nimi znamená po redelivery druhý e-mail | přijaté riziko: SMTP nemá idempotenci; hlavička `X-Eventor-Delivery` umožní duplicitu dohledat; přesně jednou by vyžadovalo poskytovatele s idempotenčním klíčem |
| W3 (review) | cizí klíče napříč schématy s `ON DELETE RESTRICT` | Registration, Communication a Payments drží id cizích modulů zatím bez FK | doplní souhrnná migrace ve W4, až existují všechny odkazované tabulky |
| W3 Registration | tabulka objednávek `order` | `registration.orders` | `order` je v SQL rezervované slovo; citovaný název rozbíjel surové SQL a kontrolu mapování |
| W3 Registration | samoobslužný token náhodný | tajemství odvozené HMAC z id tokenu, ukládá se jen SHA-256; jediný chybový kód `token_invalid` | `SelfServiceLinks::urlFor()` umí odkaz sestavit znovu bez uloženého tajemství (rozhodnutí 10 kontraktů) |
| W3 Registration | honeypot vrací falešný úspěch | honeypot vrací 422 `spam_suspected`; formulářový token se kontroluje jen, když přijde | jednodušší chování pro frontend; ochranu nese rate limit |
| W3 Registration | — | obnova samoobslužného odkazu vrací vždy 202, i po překročení limitu | žádné vyčíslování e-mailů (nález review) |
| W3 Registration | S15 přepne účasti do `requires_attention` | nastaví `legal_state = reconfirmation_required`, stav účasti zůstává | potvrzení je právní stav, ne stav životního cyklu (kniha, kap. 05) |
| W3 Registration | — | automatické obnovení po pozdní platbě obnoví buď všechny propadlé účasti objednávky, nebo žádnou; každá účast se vrací do kapacity atomicky (savepoint) | žádné částečně zabrané kapacity (nález review) |
| W3 Registration | `participation_cancelled` nese důvod | nese uzavřený kód `CancellationReason`, volný text zůstává v Registration | volný text může obsahovat zdravotní údaje (nález review) |
| W3 Registration | — | cizí klíče migrace jsou `DEFERRABLE` | alokátor zapisuje nároky na kapacitu před flushem ORM |
| W3 Communication | potvrzení objednávky a platební údaje zvlášť | objednávka převodem dostane jedno potvrzení až s `payment_request_issued` (platební údaje a QR); `order_placed` posílá potvrzení jen hotovosti a zdarma | jeden e-mail místo dvou, platební údaje jsou v něm vždy |
| W3 Communication | upomínky platby řídí Payments | upomínky (PAY-09) i připomínky před akcí plánuje Communication z událostí a odesílá jeden minutový sweeper; offsety −72 h a −24 h před splatností, 24 h před aktivitou | stav platby se před odesláním znovu ověří; žádná upomínka po úhradě |
| W3 Communication | — | kampaně jsou celoorganizátorské, člen omezený na akce dostane 403; odebrání šablony nebo potlačení se neaudituje | jednodušší první verze |
| W3 Communication | — | `CampaignAudience` nese `checkedIn` | závěrečná zpráva jen odbaveným (POST-05) |
| W3 CheckIn | QR token podepisuje CheckIn | podpis i verzi QR drží Registration (`ParticipationQrTokens`), CheckIn ho jen ověřuje | token vydává ten, kdo vlastní účast |
| W3 CheckIn | CheckIn odebírá zrušení, potvrzení a změnu varianty | neodebírá; autoritou je živá fasáda Registration v okamžiku odbavení | žádná zastaralá projekce na bráně |
| W3 CheckIn | — | jedno platné odbavení na účast a seznam: částečný unikátní index + advisory lock; zrušení a oprava jsou nové řádky (append-only), zrušení vyžaduje `voided_at`, `voided_by` a `void_reason` současně | souběh S10 a dohledatelnost |
| W3 Shared | `ModuleBoundaryPass` | inlinovaná služba se posuzuje jako vstříknutá služba podle deklarovaného typu, její závislosti zvlášť za její modul | vnitřnosti cizí fasády nepatří konzumentovi |
| W3 Payments | — | platební údaje odvodí číslo účtu z českého IBAN, když ho Pairrot nepošle | Pairrot může oznámit jen IBAN |
| W4 Content | galerie i k nezveřejněné akci | kolekce a materiály jen u zveřejněné (nebo pozdější) akce | koncept nemá galerii; fasáda Catalogu drafty nevrací |
| W4 Content | podepsané, časově omezené URL fotek | fotky vydává endpoint `/api/public/content/photos/{id}` s kontrolou zveřejnění; fotka kolekce „jen přes odkaz“ potřebuje `?token=` a nesmí do sdílené cache | bez dalšího tajemství; UUID v7 a token stačí |
| W4 Content | náhledy přes transport `async` | náhled vzniká z odběru vlastní události `content.photo_uploaded` (worker, GD, 480 px) | transport `content_events` je asynchronní stejně; odpovídá plánovanému odběrateli v katalogu |
| W4 Content | — | veřejný archiv (POST-07) ukazuje jen zveřejněné položky označené `in_archive` u dokončené nebo archivované akce (`EventCatalog::resolveArchivedSlug`); viditelnost „jen registrovaní“ (POST-03) je P1 | archiv bez osobních údajů |
