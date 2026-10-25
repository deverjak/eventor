# Event platforma — Zadání V3 (konsolidované) a rozpis features

**Verze:** 3.0 · **Datum:** 24. 10. 2026 · **Stav:** podklad pro solution architecture
**Charakter:** produktové zadání, technologicky neutrální (nepředepisuje framework, DB ani architekturu)

---

## 0. Účel dokumentu

Tento dokument nahrazuje zadání V1 a V2. Slouží jako jediný zdroj pravdy pro:

- návrh solution architecture (doménové moduly, integrace, konzistence, asynchronní procesy),
- rozpad na implementační práci (feature ID → P0/P1/P2),
- akceptační scénáře, kterými se prokazuje chování mimo CRUD.

Produkt: webová platforma, kde pořadatel založí událost, dostane odkaz, rozešle ho a na jednom místě řeší registrace, platby (bankovní převod přes externí službu **Pairrot**, hotovost, zdarma), komunikaci, program, odbavení, právní dokumenty/souhlasy a materiály po akci.

**Produktový slib:** *Vytvořte událost, nastavte registrace a sdílejte odkaz. Účastníci se přihlásí a zaplatí převodem nebo hotově. Vy spravujete přihlášky, program, komunikaci, odbavení i materiály po skončení akce.*

---

## 1. Review předchozích iterací a co se mění ve V3

| Oblast | V1 | V2 | V3 (rozhodnutí) |
|---|---|---|---|
| Struktura události | událost → kategorie → registrace | událost → aktivity → varianty → účasti | zachováno z V2, zafixován slovník (kap. 2), přidány vlny a kapacitní úrovně s atomickou kontrolou |
| Pojmy | kategorie = cenová úroveň | kategorie = věková klasifikace, varianta = cenová úroveň | závazný slovník; „kategorie“ se používá **jen** pro klasifikaci účastníka |
| Kapacita / rezervace | „dočasně držet místo“ | „pořadatel definuje, zda blokují“ | explicitní model rezervace s TTL, pravidla expirace, opožděná platba → výjimka (kap. 5.5, 7.5) |
| Pairrot | „zajišťuje párování“ | „existující externí systém“ | definován integrační kontrakt (požadavek ven, platební událost dovnitř), rekonciliační fronta, chování při výpadku (kap. 7.2) |
| Samoobsluha účastníka | „bezpečný odkaz“ | totéž | tokenový model, obnova odkazu, rozsah oprávnění odkazu (kap. 7.6) |
| Odbavení | QR identifikuje účastníka | více účastí per osoba | podepsaný QR token, odbavovací seznamy per aktivita, matice výjimek (kap. 7.11) |
| GDPR | dokumenty + verze + souhlasy | vrstvy org/akce/aktivita, typy požadavků | + retenční třídy, zvláštní kategorie údajů ve vlastních polích, převod účasti = nová potvrzení, veřejná startovka jako opt-in (kap. 7.7–7.8) |
| Ztracené featury V1 | čekací listina, slevy, zrušení akce | vypadly | vráceny: čekací listina (P1), slevy/promo kódy (P1), zrušení akce jako proces (P0 lite) |
| Oprávnění | slovní popis rolí | 4 role | matice akce × role (kap. 3) |
| Scénáře mimo CRUD | 8 | 8 nových | sloučeno a rozšířeno na 18 (kap. 11) |

Co se **nemění**: Pairrot zůstává samostatnou službou (appka neduplikuje párování), účastník nepotřebuje účet, jednoduchost je výchozí stav, GDPR je páteřní feature dema, časomíra/turnikety/karty/marketplace jsou mimo rozsah.

---

## 2. Slovník pojmů (závazný)

| Pojem | Význam |
|---|---|
| **Organizátor** (tenant) | Subjekt, který pořádá akce. Vlastní data, nastavení, tým, právní dokumenty, platební nastavení. Data organizátorů jsou oddělená. |
| **Člen týmu** | Uživatelský účet s rolí v rámci organizátora. Jeden účet může mít role u více organizátorů. |
| **Událost** | To, co organizátor propaguje pod jedním veřejným odkazem (např. „Víkend trailových závodů 2026“). Může být jedno- i vícedenní. |
| **Aktivita** | Konkrétní registrovatelná část události (např. „Sobota — Trail 10 km“, „Neděle — Workshop první pomoci“). Má vlastní termín, kapacitu, pravidla, dokumenty. Jednoduchá událost má právě jednu aktivitu (skrytou v UI). |
| **Varianta** | Způsob účasti v rámci aktivity s vlastní cenou/kapacitou/pravidly (Jednotlivec / Dvojice / Tým; Běžný / Student / Doprovod). |
| **Vlna (slot)** | Volitelné časové rozdělení aktivity s vlastní kapacitou (start 9:00 / 9:30). |
| **Kategorie** | Klasifikace účastníka odvozená z údajů (typicky věk podle data narození). Není to volba účastníka, ale výsledek pravidla. |
| **Programový bod** | Položka harmonogramu. Nemusí být registrovatelná. Aktivita se může v programu zobrazovat, vazba je volitelná. |
| **Objednávka** | Jednotka platby a komunikace. Obsahuje 1..N účastí a 0..N doplňkových položek. Má jednoho objednávajícího. |
| **Objednávající** | Osoba, která objednávku vytvořila a je kontaktní pro platbu. Může, ale nemusí být účastníkem. |
| **Osoba** | Fyzická osoba uvedená v objednávce (účastník). Má registrační údaje per osoba. |
| **Účast** | Vazba osoba × aktivita × varianta (× vlna). Je nositelem kapacity, odbavení, QR kódu a právních potvrzení vázaných na aktivitu. Rodič + 2 děti na různé dny = 3 účasti v 1 objednávce. |
| **Skupina / tým** | Množina účastí s týmovou variantou (název týmu, kontaktní osoba, členové). |
| **Doplňková položka** | Volitelná položka objednávky s cenou a případným limitem množství (tričko, oběd). Není to e-shop. |
| **Platební požadavek** | Požadavek na úhradu objednávky bankovním převodem, vytvořený přes Pairrot (částka, účet, VS, QR, splatnost). |
| **Platební událost** | Informace o příchozí platbě doručená z Pairrotu (nebo hotovostní záznam obsluhy). |
| **Rezervace místa** | Dočasné obsazení kapacity nepotvrzenou účastí s časovým limitem (TTL). |
| **Právní dokument** | Verzovaný dokument (text / PDF / neměnná kopie externího obsahu) s úrovní platnosti (organizátor / událost / aktivita). |
| **Právní požadavek** | Konkrétní požadavek při registraci: informace / podmínky / souhlas / svolení k podobizně / prohlášení. Váže se na dokument a verzi. |
| **Potvrzení** | Záznam rozhodnutí konkrétní osoby ke konkrétnímu právnímu požadavku a verzi dokumentu, ve vazbě na účast nebo objednávku. |
| **Odbavení (check-in)** | Záznam fyzického příchodu k dané účasti. |
| **Odbavovací seznam** | Množina účastí, které smí být odbaveny na daném stanovišti (typicky = aktivita nebo den). |
| **Výjimka** | Situace vyžadující rozhodnutí oprávněné osoby (nespárovaná platba, opožděná platba, chybějící potvrzení…). Zobrazuje se ve frontě „vyžaduje pozornost“. |

---

## 3. Role a oprávnění

### 3.1 Role

- **Vlastník organizace** — vše včetně nastavení organizace, týmu, platebních údajů, právních dokumentů, exportů.
- **Správce akce** — konfigurace přiřazených událostí, registrace, program, komunikace, výjimky, storna.
- **Finanční obsluha** — platby, hotovost, rekonciliace, refund úkoly, finanční reporty.
- **Obsluha odbavení** — vyhledávání účastí, odbavení, evidence hotovosti (volitelně), omezený pohled na osobní údaje.
- **Účastník / objednávající** — bez účtu, pracuje přes tokenový odkaz.

Role se přidělují na úrovni organizátora; správce akce a obsluha odbavení mohou být omezeni na konkrétní události.

### 3.2 Matice oprávnění

| Akce | Vlastník | Správce akce | Finance | Odbavení |
|---|:-:|:-:|:-:|:-:|
| Nastavení organizace, bankovní účet, Pairrot | ✔ | – | – | – |
| Správa týmu a rolí | ✔ | – | – | – |
| Právní dokumenty organizátora (nová verze) | ✔ | – | – | – |
| Vytvořit / upravit / publikovat událost | ✔ | ✔ | – | – |
| Právní dokumenty události / aktivity | ✔ | ✔ | – | – |
| Konfigurace formuláře, aktivit, kapacit, cen | ✔ | ✔ | – | – |
| Zobrazit účastníky (plné osobní údaje) | ✔ | ✔ | ✔ | – |
| Zobrazit účastníky (odbavovací pohled: jméno, aktivita, stavy, poznámka pro odbavení) | ✔ | ✔ | ✔ | ✔ |
| Export CSV osobních údajů | ✔ | ✔ | – | – |
| Upravit registrační údaje, storno, převod | ✔ | ✔ | – | – |
| Řešit platební výjimky, rekonciliace | ✔ | ✔* | ✔ | – |
| Evidovat hotovost | ✔ | ✔ | ✔ | ✔ (pokud povoleno) |
| Opravit hotovostní záznam | ✔ | – | ✔ | – |
| Refund úkoly (vytvořit / uzavřít) | ✔ | ✔ / – | ✔ | – |
| Odbavit účast | ✔ | ✔ | – | ✔ |
| Opravit / zrušit odbavení | ✔ | ✔ | – | – |
| Vytvořit a odeslat zprávu | ✔ | ✔ | – | – |
| Program a aktuality | ✔ | ✔ | – | – |
| Galerie a materiály | ✔ | ✔ | – | – |
| Žádosti subjektů údajů (export / oprava / výmaz) | ✔ | – | – | – |
| Zobrazit audit log | ✔ | ✔ (vlastní události) | ✔ (finanční) | – |
| Zrušit událost | ✔ | – | – | – |

\* Správce akce může výjimku **přiřadit** nebo označit k řešení, finální finanční rozhodnutí patří Finance/Vlastník. Lze zjednodušit konfigurací organizátora.

---

## 4. Doménový model (produktová úroveň)

Není to návrh tabulek; je to množina pojmů a vazeb, které musí systém vyjádřit.

```
Organizátor
 ├─ Člen týmu (účet × role × [událost])
 ├─ Nastavení plateb (účet, Pairrot napojení, výchozí splatnost, TTL rezervace)
 ├─ Právní dokument (úroveň: organizátor) ── Verze dokumentu (neměnná)
 └─ Událost
     ├─ Stav zveřejnění, stav registrací (nezávislé)
     ├─ Místa konání (1..N)
     ├─ Verze formuláře (per událost, snapshot schématu)
     ├─ Právní dokument (úroveň: událost)
     ├─ Aktivita (1..N; v jednoduchém režimu 1 implicitní)
     │   ├─ Termín, místo, kapacita, stav registrací
     │   ├─ Varianta (1..N: cena, kapacita, počet osob, pravidla)
     │   ├─ Vlna (0..N: čas, kapacita)
     │   ├─ Pravidlo kategorií (věkové hranice, referenční datum)
     │   ├─ Právní dokument (úroveň: aktivita)
     │   └─ Odbavovací seznam (default 1 = aktivita)
     ├─ Doplňková položka (0..N)
     ├─ Programový bod (0..N) ──(volitelně)── Aktivita
     ├─ Aktualita (0..N)
     ├─ Zpráva / kampaň (0..N) ── Doručení (per příjemce)
     ├─ Galerie / kolekce (0..N) ── Fotografie | Externí odkaz
     ├─ Materiál / odkaz (0..N)
     └─ Objednávka (0..N)
         ├─ Objednávající (kontakt)
         ├─ Osoba (1..N) ── Odpovědi (scope: osoba)
         ├─ Účast (1..N) = Osoba × Aktivita × Varianta × [Vlna] × [Skupina]
         │   ├─ Odpovědi (scope: účast)
         │   ├─ Potvrzení právních požadavků (per účast)
         │   ├─ Rezervace místa (TTL) → stav účasti
         │   ├─ QR token
         │   └─ Odbavení (0..N záznamů, právě 1 platný)
         ├─ Skupina (0..N) ── Odpovědi (scope: skupina)
         ├─ Položka objednávky (0..N doplňky)
         ├─ Odpovědi (scope: objednávka)
         ├─ Potvrzení právních požadavků (per objednávka)
         ├─ Platební požadavek (0..1 Pairrot)
         ├─ Platební událost (0..N: Pairrot | hotovost | oprava)
         ├─ Finanční stav (odvozený)
         └─ Refund úkol (0..N)

Průřezové: Audit záznam, Výjimka (fronta), Žádost subjektu údajů, Retenční pravidlo, Token samoobsluhy, Idempotenční klíč.
```

Klíčové invarianty:

- Účast je **jediný** nositel kapacity a odbavení. Objednávka nositelem není.
- Potvrzení se váže na **verzi** dokumentu, konkrétní osobu a konkrétní účast/objednávku; nikdy se nepřepisuje, jen přibývá (nové rozhodnutí / odvolání).
- Odpovědi formuláře se ukládají spolu s identifikací **verze formuláře**, ve které vznikly.
- Finanční stav objednávky je **odvozený** z platebních událostí; ruční zásah je nová platební událost (oprava), ne přepis.
- Každá externí nebo opakovatelná operace (platební událost, odbavení, odeslání zprávy) nese idempotenční klíč.

---

## 5. Stavové modely

Stavy jsou oddělené dimenze; nesmí se slučovat do jednoho pole.

### 5.1 Událost — zveřejnění
`koncept → publikovaná → (zrušená | dokončená → archivovaná)`
Zrušení je proces: informuje účastníky, vytvoří refund úkoly pro zaplacené objednávky, uzavře registrace.

### 5.2 Registrace (na úrovni události i aktivity)
`neotevřené → otevřené → pozastavené → uzavřené`
Nezávislé na zveřejnění. Aktivita může mít registrace uzavřené, zatímco jiná aktivita téže události je otevřená.

### 5.3 Účast
- `čeká` (vytvořena, drží rezervaci s TTL — bankovní převod)
- `potvrzená` (zaplaceno / hotovost na místě / zdarma / ručně potvrzeno)
- `expirovaná` (TTL vypršel bez úhrady, místo uvolněno)
- `stornovaná` (účastníkem nebo pořadatelem)
- `vyžaduje řešení` (např. platba po expiraci, chybějící potvrzení po změně pravidel)
- `převedená` (na jinou osobu; původní účast končí, vzniká nová)

Přechody mimo automatiku jsou vždy auditované.

### 5.4 Objednávka — finanční stav (odvozený)
`neuhrazená | částečně uhrazená | uhrazená | přeplacená | sporná | vrácení čeká | vráceno`
Pravidla: součet přijatých platebních událostí vs. aktuální dlužná částka objednávky (ta se může změnit stornem / změnou varianty → může vzniknout přeplatek nebo nedoplatek).

### 5.5 Rezervace místa
- Vzniká při vytvoření účasti, pokud varianta/aktivita je placená převodem a pořadatel zapnul držení místa (výchozí: ano).
- TTL konfigurovatelný per událost (výchozí = splatnost platebního požadavku, např. 3 dny; nejpozději do uzavření registrací / dne akce).
- Expirace: účast → `expirovaná`, kapacita se uvolní, odejde notifikace. Čekací listina (P1) dostane nabídku.
- Opožděná platba po expiraci: platební událost se spáruje s objednávkou, ale účast se **neobnoví automaticky**. Vznikne výjimka: je-li kapacita volná, oprávněná osoba obnoví jedním krokem; není-li, vznikne refund úkol. Pořadatel může per událost zvolit „automaticky obnovit, pokud je volná kapacita“.
- Hotovost na místě a zdarma: účast je `potvrzená` ihned (rezervace se nepoužije), kapacita obsazena natrvalo.

### 5.6 Odbavení
`neodbaven → odbaven → (opraveno / zrušeno → neodbaven)`
Vždy právě jeden platný záznam odbavení; opravy jsou nové auditované záznamy.

### 5.7 Právní potvrzení
`vyžádáno → uděleno | odmítnuto (u volitelných) → odvoláno (u odvolatelných)`
Nová verze dokumentu s příznakem „vyžaduje nové potvrzení“ → stav `vyžádáno znovu`, původní záznam zůstává.

### 5.8 Doručení zprávy (per příjemce)
`naplánováno → odesláno → doručeno | selhalo → (opakováno) | přeskočeno (příjemce už nesplňuje podmínku)`

---

## 6. Hlavní uživatelské scénáře (end-to-end)

1. **Pořadatel založí jednoduchou akci** (průvodce: název, termín, místo, kapacita, cena, platby, výchozí dokumenty) → publikuje → kopíruje odkaz/QR.
2. **Pořadatel založí vícedenní akci** s několika aktivitami, variantami a různými dokumenty per aktivita.
3. **Účastník se registruje** (sám / za více osob / na více aktivit) → vidí rozpis ceny → potvrdí jen relevantní dokumenty → vybere způsob platby → dostane potvrzení + platební údaje + odkaz na samoobsluhu.
4. **Pairrot potvrdí platbu** → objednávka uhrazená → účasti potvrzené → potvrzovací e-mail.
5. **Pořadatel komunikuje**: pokyny před akcí (cílené per aktivita), změna programu → notifikace dotčeným, aktuality na stránce.
6. **Den akce**: obsluha odbavuje (QR / jméno / kód), eviduje hotovost, vidí provozní přehled.
7. **Po akci**: galerie / externí odkazy, závěrečná zpráva odbaveným, report, archivace; retence údajů.
8. **Průřezově**: změna dokumentu, odvolání souhlasu, žádost o výmaz, storno, převod účasti, výjimky plateb.

---

## 7. Funkční požadavky (po modulech, s feature ID)

Priorita: **P0** = demo musí mít funkční, **P1** = další verze, **P2** = růst. Priority souhrnně v kap. 12.

### 7.1 M01 — Organizace a tým (ORG)

| ID | Požadavek | Prio |
|---|---|---|
| ORG-01 | Založení organizátora: název, logo, kontakt, fakturační/identifikační údaje, kontaktní e-mail pro účastníky. | P0 |
| ORG-02 | Platební nastavení: bankovní účet, napojení na Pairrot, výchozí splatnost, výchozí TTL rezervace. | P0 |
| ORG-03 | Tým: pozvání člena e-mailem, přiřazení role, volitelné omezení na události, odebrání. | P0 |
| ORG-04 | Jeden účet může být členem více organizátorů; přepínání kontextu. | P1 |
| ORG-05 | Výchozí vizuální styl veřejných stránek (barva, logo, titulní obrázek). | P1 |
| ORG-06 | Výchozí právní dokumenty organizátora dědí nové události (viz LEG). | P0 |
| ORG-07 | Veřejný profil organizátora s přehledem akcí. | P2 |

### 7.2 M02 — Události a struktura (EVT)

| ID | Požadavek | Prio |
|---|---|---|
| EVT-01 | Vytvoření události: název, krátký/detailní popis, titulní obrázek, termín od–do, místa konání (1..N, odkaz na mapu), kontaktní osoba. | P0 |
| EVT-02 | Průvodce „jednoduchá akce“ (1 implicitní aktivita, 1 varianta) vs. „akce s více aktivitami“. Rozšířené možnosti skryté, dokud nejsou potřeba. | P0 |
| EVT-03 | Stav zveřejnění (koncept / publikovaná / zrušená / dokončená / archivovaná) nezávislý na stavu registrací. | P0 |
| EVT-04 | Stav registrací na úrovni události a aktivity: termín otevření/uzavření, ruční pozastavení. | P0 |
| EVT-05 | Aktivita: název, popis, datum/čas, místo, kapacita, stav registrací, vlastní pravidla, vlastní dokumenty. | P0 |
| EVT-06 | Varianta: název, cena, kapacita, počet osob (1 = jednotlivec, N = tým/dvojice), požadovaná pole, podmínky účasti (např. věk). | P0 |
| EVT-07 | Vlny/sloty: časové rozdělení aktivity s vlastní kapacitou; účastník volí vlnu. | P1 |
| EVT-08 | Kategorie účastníka: konfigurovatelné věkové hranice, referenční datum (den akce / kalendářní rok), ruční oprava oprávněnou osobou. | P1 |
| EVT-09 | Doplňkové položky: název, cena, limit množství, scope (per objednávka / per osoba). | P1 |
| EVT-10 | Pravidla plateb a storna per událost (povolené metody, splatnost, TTL, stornopodmínky, chování opožděné platby). | P0 |
| EVT-11 | Viditelnost: veřejná / neveřejná (jen přes odkaz). | P0 |
| EVT-12 | Zrušení události jako proces: potvrzení, uzavření registrací, hromadná notifikace, refund úkoly. | P0 (lite) |
| EVT-13 | Šablony a kopírování ročníku (struktura, program, formulář, dokumenty — **bez** osobních údajů a souhlasů). | P2 |
| EVT-14 | Seriály akcí (více událostí pod společnou hlavičkou). | P2 |
| EVT-15 | Slevy: promo kód, sleva při více aktivitách v jedné objednávce, časové (early bird). | P1 |

### 7.3 M03 — Veřejná stránka (PUB)

| ID | Požadavek | Prio |
|---|---|---|
| PUB-01 | Vlastní URL události (slug v rámci organizátora), sdílitelný odkaz, QR kód na stránku. | P0 |
| PUB-02 | Obsah: název, termín, místo (mapa), popis, přehled aktivit/variant s cenami a dostupností, stav registrací, program, aktuality, kontakt, právní dokumenty. | P0 |
| PUB-03 | Zobrazení dostupnosti kapacity (volno / posledních X / obsazeno / čekací listina). | P0 |
| PUB-04 | Mobilní responzivita, přístupnost formulářů. | P0 |
| PUB-05 | Veřejný seznam účastníků (startovka) — pouze osoby, které udělily opt-in souhlas se zveřejněním. | P1 |
| PUB-06 | Galerie a materiály po akci (viz POST). | P0 |
| PUB-07 | Archivní zobrazení dokončené události. | P1 |

### 7.4 M04 — Editor registračního formuláře (FRM)

| ID | Požadavek | Prio |
|---|---|---|
| FRM-01 | Typy polí: krátký text, dlouhý text, číslo, datum, e-mail, telefon, výběr jedné, výběr více, ano/ne, informační text, právní požadavek (viz LEG). | P0 |
| FRM-02 | Atributy pole: název, nápověda, povinnost, omezení hodnot (min/max, regex pro P1), výchozí hodnota. | P0 |
| FRM-03 | Scope pole: per objednávka / per osoba / per účast / per skupina. UI nabízí srozumitelné formulace („Zeptat se každého účastníka“). | P0 |
| FRM-04 | Předdefinovaná pole se sémantikou (jméno, příjmení, datum narození, e-mail, telefon, zákonný zástupce) — systém jim rozumí (věk, kontakt, notifikace). | P0 |
| FRM-05 | Podmíněná pole: zobrazit, pokud (aktivita = X) / (varianta = Y) / (odpověď pole = Z). Jednoduchá pravidla, ne obecný rule engine. | P0 |
| FRM-06 | Vazba pole na aktivitu/variantu (pole „jméno psa“ jen pro canicross). | P0 |
| FRM-07 | Validace probíhá serverově podle schématu, ne podle viditelnosti na obrazovce. | P0 |
| FRM-08 | Verzování formuláře: každá změna po publikaci vytvoří novou verzi; odpovědi nesou verzi, ve které vznikly. | P0 |
| FRM-09 | Změna po registracích: starší záznamy zůstanou konzistentní; nové povinné pole lze doplnit výzvou k doplnění (viz COM). Nikdy nevznikne zdánlivě vyplněná odpověď ani souhlas. | P0 |
| FRM-10 | Upozornění editoru při vytváření pole, které může obsahovat zvláštní kategorii údajů (zdravotní stav, alergie) — vyžaduje právní základ a retenční třídu. | P1 |
| FRM-11 | Náhled formuláře pro vybranou kombinaci aktivit/variant. | P0 |

### 7.5 M05 — Registrace a objednávky (REG)

| ID | Požadavek | Prio |
|---|---|---|
| REG-01 | Registrační průchod: výběr aktivit/variant (více najednou) → osoby → odpovědi → právní požadavky → rekapitulace ceny → způsob platby → potvrzení. | P0 |
| REG-02 | Bez účtu účastníka. | P0 |
| REG-03 | Skupinová registrace: 1 objednávka, N osob, M účastí; každá účast vlastní aktivita/varianta/údaje/potvrzení; společná platba. | P0 |
| REG-04 | Týmová varianta: název týmu, kontakt, členové; doplnění členů později přes odkaz. | P1 |
| REG-05 | Rekapitulace: rozpis per účast a položka, slevy, celková částka, splatnost. | P0 |
| REG-06 | Nezletilý: vyžaduje údaje zákonného zástupce a jeho potvrzení vztažená ke konkrétnímu dítěti, ne ke všem osobám v objednávce. | P0 |
| REG-07 | Potvrzení registrace e-mailem s platebními údaji a odkazem na samoobsluhu. | P0 |
| REG-08 | Samoobsluha přes tokenový odkaz: zobrazit osoby a účasti, platební stav, platební údaje znovu, aktuality, dokumenty, QR kódy. | P0 |
| REG-09 | Samoobsluha – změny podle pravidel události: oprava údajů, storno účasti, žádost o změnu varianty, odvolání odvolatelného souhlasu. Změny ovlivňující cenu/kapacitu procházejí novým vyhodnocením pravidel. | P1 (storno + oprava údajů P0) |
| REG-10 | Převod účasti na jinou osobu: nová osoba vyplní vlastní údaje a potvrzení; původní osoba je odpojena; finance zůstávají na objednávce. | P1 |
| REG-11 | Obnova odkazu na samoobsluhu: zadání e-mailu → odeslání nového odkazu (bez odhalení, zda e-mail existuje). | P0 |
| REG-12 | Čekací listina: při obsazení možnost zapsat se; po uvolnění nabídka s časovým limitem na potvrzení; pořadí a historie nabídek. | P1 |
| REG-13 | Registrace pořadatelem ručně (telefonická přihláška) s označením původu. | P1 |
| REG-14 | Storno pořadatelem: důvod, dopad na finance (vzniká refund úkol / přeplatek), notifikace. | P0 |

### 7.6 M06 — Kapacity a rezervace (CAP)

| ID | Požadavek | Prio |
|---|---|---|
| CAP-01 | Kapacitní úrovně: událost, aktivita, varianta, vlna. Účast obsadí všechny relevantní úrovně. | P0 (vlna P1) |
| CAP-02 | Atomická kontrola a obsazení všech úrovní při vytvoření objednávky; souběžné registrace nesmí překročit limit. | P0 |
| CAP-03 | Rozlišení dostupné / rezervované / potvrzené kapacity ve veřejném i administračním pohledu. | P0 |
| CAP-04 | Rezervace s TTL (kap. 5.5): konfigurace, expirace, uvolnění, notifikace. | P0 |
| CAP-05 | Opožděná platba po expiraci → výjimka, obnova jen při volné kapacitě (ručně nebo dle nastavení). | P0 |
| CAP-06 | Úprava kapacity pořadatelem (i pod aktuální obsazenost — bez automatického rušení, jen varování), auditováno. | P0 |
| CAP-07 | Vícečlenná varianta obsadí počet míst odpovídající počtu osob (nebo 1 týmové místo — konfigurovatelné). | P1 |

### 7.7 M07 — Platby (PAY)

| ID | Požadavek | Prio |
|---|---|---|
| PAY-01 | Platební metody per událost: bankovní převod (Pairrot), hotovost na místě, zdarma. Karty mimo rozsah. | P0 |
| PAY-02 | Při vytvoření objednávky s převodem vznikne platební požadavek přes Pairrot: částka, účet, jednoznačný identifikátor (VS), QR platba, splatnost. | P0 |
| PAY-03 | Výpadek Pairrotu při vytváření požadavku: objednávka a účasti jsou uloženy, vytvoření požadavku se opakuje asynchronně; účastník dostane platební údaje po úspěchu (a mezitím informaci, že přijdou). | P0 |
| PAY-04 | Příjem platebních událostí z Pairrotu (spárovaná platba: identifikátor požadavku, částka, čas, externí ID platby). Zpracování je idempotentní podle externího ID. | P0 |
| PAY-05 | Vyhodnocení dopadu platby na objednávku a účasti: úplná → potvrzení účastí; částečná → stav částečně uhrazeno + upomínka na doplatek; přeplatek → výjimka (vrácení / dar dle nastavení); platba na stornovanou/expirovanou objednávku → výjimka. | P0 |
| PAY-06 | Nespárovaná / nejednoznačná platba (pokud ji Pairrot předá): fronta rekonciliace, ruční přiřazení k objednávce, auditováno. | P0 |
| PAY-07 | Hotovost: obsluha zaznamená částku, čas, kdo přijal, poznámku; vzniká platební událost. | P0 |
| PAY-08 | Oprava hotovostního záznamu = nová kompenzační událost, obě zůstávají v historii. | P0 |
| PAY-09 | Upomínky nezaplacených objednávek řízené stavem (nikdy po úhradě/stornu), konfigurovatelný harmonogram. | P0 |
| PAY-10 | Storno a změny přepočítávají dlužnou částku; výsledný nedoplatek/přeplatek se promítne do finančního stavu. | P0 |
| PAY-11 | Refund úkol: vzniká ze storna/přeplatku/zrušení akce; obsahuje částku, důvod, stav (otevřeno / vypořádáno) a kdo uzavřel. Vrácení peněz je manuální mimo systém. | P0 |
| PAY-12 | Potvrzení o přijaté platbě (PDF) — není to daňový doklad. | P1 |
| PAY-13 | Finanční přehled události: očekáváno / přijato převodem / přijato hotově / dluží / přeplatky / k vrácení. | P0 |

**Předpoklady k integraci Pairrot (ověřit proti reálnému API):** způsob vytvoření požadavku a vrácení identifikátoru; doručování platebních událostí (webhook vs. polling); dostupnost externího ID platby pro idempotenci; co Pairrot dělá s nespárovanými platbami; jak hlásí částečné úhrady a přeplatky; režim pro testovací prostředí dema.

### 7.8 M08 — Právní dokumenty a souhlasy (LEG)

| ID | Požadavek | Prio |
|---|---|---|
| LEG-01 | Dokument: vložený text, nahrané PDF nebo externí odkaz; u externího odkazu se ukládá neměnná kopie obsahu v okamžiku publikace verze. | P0 |
| LEG-02 | Úroveň platnosti: organizátor / událost / aktivita; nižší úroveň může přidat nebo nahradit dokument. UI ukazuje, co se při dané registraci použije. | P0 |
| LEG-03 | Verzování: každá publikovaná verze je neměnná a dohledatelná; registrace odkazuje na konkrétní verzi. | P0 |
| LEG-04 | Typy právních požadavků: (a) informace k seznámení, (b) podmínky vyžadující potvrzení, (c) dobrovolný odvolatelný souhlas pro účel, (d) svolení k pořízení/užití podobizny, (e) prohlášení vyžadované aktivitou (zdravotní způsobilost). Nelze je sloučit do jednoho checkboxu. | P0 |
| LEG-05 | Vlastnosti požadavku: název, vysvětlení účelu, rozsah, doba platnosti, odvolatelnost, kdo potvrzuje (objednávající / každý účastník / zákonný zástupce), vazba na aktivitu/variantu. | P0 |
| LEG-06 | Dobrovolné souhlasy nejsou předzaškrtnuté a nejsou podmínkou registrace; systém blokuje nastavení „povinný marketingový souhlas“ a varuje u sporných kombinací. | P0 |
| LEG-07 | Záznam potvrzení: požadavek, verze dokumentu, osoba, účast/objednávka, čas, rozhodnutí, kanál (web / ručně obsluhou s poznámkou). | P0 |
| LEG-08 | Nová verze s příznakem „vyžaduje nové potvrzení“: dotčené registrace přejdou do `vyžaduje řešení`, odejde výzva, mlčení ≠ souhlas. Bez příznaku: jen informativní změna. | P0 |
| LEG-09 | Odvolání odvolatelného souhlasu účastníkem přes samoobsluhu; nezrušuje registraci; vytvoří notifikaci pořadateli a u mediálního svolení provozní úkol (prověřit publikované fotografie). | P0 |
| LEG-10 | Přehled souhlasů per událost: kdo co udělil/odvolal, filtrovatelné (např. „účastníci bez souhlasu s publikací fotek“). | P0 |
| LEG-11 | Nezletilí: požadavky typu (b)/(c)/(d)/(e) u dítěte potvrzuje zákonný zástupce, vazba na konkrétní dítě. | P0 |
| LEG-12 | Převod účasti vyžaduje nová potvrzení od nové osoby. | P1 |
| LEG-13 | Knihovna vzorů požadavků (ne právních textů) pro rychlý start: podmínky účasti, GDPR informace, fotky, marketing. | P1 |

### 7.9 M09 — Osobní údaje: práva subjektů a retence (PRV)

| ID | Požadavek | Prio |
|---|---|---|
| PRV-01 | Vyhledání všech údajů konkrétní osoby napříč událostmi organizátora. | P1 |
| PRV-02 | Export údajů osoby ve strojově čitelné podobě. | P1 |
| PRV-03 | Evidence žádostí subjektů (oprava / výmaz / omezení / námitka): stav, kdo řeší, lhůta, výsledek. | P1 |
| PRV-04 | Výmaz / anonymizace: odstraní registrační údaje, zachová údaje nutné z právních důvodů (finanční záznamy) v oddělené retenční třídě; auditováno. | P1 |
| PRV-05 | Retenční třídy: registrační údaje, finanční záznamy, záznamy potvrzení, média. Každá s vlastním pravidlem (např. N měsíců po skončení akce). | P1 |
| PRV-06 | Automatická anonymizace po uplynutí retence s předchozím upozorněním pořadateli. | P2 |
| PRV-07 | Oddělení nezbytné organizační komunikace (právní základ smlouva/oprávněný zájem) od marketingu (souhlas) v adresování zpráv. | P0 |

### 7.10 M10 — Program a aktuality (PRG)

| ID | Požadavek | Prio |
|---|---|---|
| PRG-01 | Harmonogram po dnech: název, čas od–do, místo/stanoviště, popis, odpovědná osoba, viditelnost (veřejné / jen registrovaní / vybrané aktivity). | P0 |
| PRG-02 | Volitelná vazba programového bodu na aktivitu; aktivita se může do programu propsat automaticky. | P0 |
| PRG-03 | Úprava po zveřejnění; při označení změny jako „důležitá“ nabídka notifikovat dotčené účastníky (podle vazby na aktivitu). | P0 |
| PRG-04 | Aktuality: text, čas zveřejnění, autor, priorita, volba „zároveň rozeslat“. | P0 |
| PRG-05 | Rezervace míst na programové bloky (workshopy) — jako aktivita/vlna, ne jako nová entita. | P2 |

### 7.11 M11 — Komunikace a notifikace (COM)

| ID | Požadavek | Prio |
|---|---|---|
| COM-01 | Transakční zprávy řízené stavem: registrace přijata, platební údaje, upomínka, platba potvrzena, částečná platba, expirace, storno, připomenutí před akcí (per aktivita), změna aktivity/termínu, zrušení akce, výzva k doplnění údajů/potvrzení, materiály po akci. | P0 |
| COM-02 | Pravidlo: zpráva se odešle jen pokud příjemce v okamžiku odeslání stále splňuje podmínku (upomínka neodejde po úhradě). | P0 |
| COM-03 | Vlastní zprávy pořadatele: editor, náhled, počet příjemců, odeslat / naplánovat / koncept. | P0 |
| COM-04 | Cílení: událost, aktivita, varianta, stav účasti, stav platby, stav odbavení, odpověď na pole, souhlas (marketing jen s opt-in). | P0 (odpověď na pole P1) |
| COM-05 | Odesílatel: jméno organizátora, reply-to kontaktní e-mail organizátora; technický odesílatel platformy. | P0 |
| COM-06 | Historie doručení per příjemce (stav, čas, chyba), možnost opakovat jen selhané bez duplicit. | P0 |
| COM-07 | Výpadek e-mailové služby: zprávy čekají ve frontě, registrace není ovlivněna, opakování s omezením. | P0 |
| COM-08 | Šablony s proměnnými (jméno, aktivita, částka, odkaz na samoobsluhu, QR). | P0 |
| COM-09 | Odhlášení z marketingových zpráv jedním klikem; transakční zprávy odhlášení nemají. | P1 |
| COM-10 | SMS kanál. | P2 |

### 7.12 M12 — Odbavení (CHK)

| ID | Požadavek | Prio |
|---|---|---|
| CHK-01 | Zjednodušený režim pro obsluhu (mobil/tablet), výběr odbavovacího seznamu (aktivita/den). | P0 |
| CHK-02 | Vyhledání podle jména, registračního kódu, načtení QR kamerou. | P0 |
| CHK-03 | QR nese podepsaný token účasti (ne surové ID); platnost vázaná na účast; při zrušení účasti je neplatný. | P0 |
| CHK-04 | Detail před potvrzením: jméno, aktivita/varianta/vlna, stav účasti, stav platby, chybějící potvrzení, kategorie, poznámka pro odbavení, další účasti téže osoby. | P0 |
| CHK-05 | Potvrzení příchodu: čas, kdo, stanoviště. | P0 |
| CHK-06 | Souběh: dvě obsluhy stejný kód → právě jeden platný záznam, druhá vidí „již odbaveno v HH:MM kým“. | P0 |
| CHK-07 | Oprava/zrušení odbavení jako auditovaná operace (správce). | P0 |
| CHK-08 | Hotovost při odbavení (pokud má obsluha oprávnění), s okamžitou změnou finančního stavu. | P0 |
| CHK-09 | Matice výjimek: již odbaven / nezaplaceno / neúplná registrace / chybějící potvrzení / stornováno / špatná aktivita — pravidla, co obsluha řeší sama a co eskaluje. | P0 |
| CHK-10 | Provozní přehled: očekáváno / odbaveno / chybí / nezaplaceno / hotovost přijata, per aktivita. | P0 |
| CHK-11 | Více stanovišť s vlastními odbavovacími seznamy. | P1 |
| CHK-12 | Offline režim s pozdější synchronizací. | P2 |

### 7.13 M13 — Obsah po akci (POST)

| ID | Požadavek | Prio |
|---|---|---|
| POST-01 | Interní galerie: nahrání fotografií, popis, autor, den/část události, pořadí; náhledy. | P0 |
| POST-02 | Externí kolekce: odkaz na galerii fotografa/úložiště s metadaty. | P0 |
| POST-03 | Viditelnost kolekce: veřejná / jen přes odkaz / jen registrovaní (přes samoobsluhu). | P0 (jen registrovaní P1) |
| POST-04 | Materiály a odkazy: výsledky (externí), video, PDF, poděkování partnerům. | P0 |
| POST-05 | Závěrečná zpráva cílená (např. jen odbaveným) s odkazy na materiály. | P0 |
| POST-06 | Vazba na souhlasy: při publikaci upozornění na počet účastníků bez mediálního svolení; odebrání fotografie na žádost (interní galerie). | P1 |
| POST-07 | Dokončení a archivace události; veřejný archiv obsahuje jen určený obsah, nikdy osobní údaje. | P0 |

### 7.14 M14 — Administrace, přehledy, reporting (ADM)

| ID | Požadavek | Prio |
|---|---|---|
| ADM-01 | Dashboard organizace: nadcházející/dokončené akce, registrace, otevřené výjimky, poslední aktivity. | P0 |
| ADM-02 | Dashboard události: obsazenost per aktivita, registrace (potvrzené/čekající), finance, odbavení, hotovost. | P0 |
| ADM-03 | Fronta „vyžaduje pozornost“: nespárované platby, opožděné platby, přeplatky, nedoručené zprávy, chybějící potvrzení, žádosti o storno, refund úkoly, odvolané souhlasy. Každá položka má akci. | P0 |
| ADM-04 | Správa registrací: tabulka, vyhledávání, filtry (aktivita, varianta, stavy, metoda platby, datum, vlastní pole), detail, poznámka, znovu odeslat potvrzení. | P0 |
| ADM-05 | Dynamické sloupce z vlastních polí a filtrování podle typu pole. | P1 |
| ADM-06 | Hromadné operace nad výběrem (odeslat zprávu, exportovat, označit). | P0 (zpráva) / P1 (ostatní) |
| ADM-07 | Export CSV (registrace, finance, odbavení, souhlasy) respektující oprávnění. | P0 |
| ADM-08 | Report po akci: registrace vs. příchozí, per aktivita/varianta/kategorie, příjmy, dluhy, storna, výjimky, nevyužitá místa; export. | P0 |
| ADM-09 | Porovnání ročníků, konverze registračního průchodu. | P2 |

### 7.15 M15 — Audit (AUD)

| ID | Požadavek | Prio |
|---|---|---|
| AUD-01 | Auditní záznam pro: změnu kapacity, ceny, stavu účasti, platební zásah, hotovost a opravy, storno, převod, změnu/verzi dokumentu, potvrzení a odvolání souhlasu, odbavení a opravy, změnu oprávnění, export osobních údajů, žádosti subjektů, zrušení akce. | P0 |
| AUD-02 | Záznam obsahuje: kdo (uživatel / systém / účastník přes token), kdy, co (entita), před/po nebo popis, důvod (u ručních zásahů povinný). | P0 |
| AUD-03 | Rozlišení automatických změn od manuálních zásahů. | P0 |
| AUD-04 | Zobrazení historie v detailu objednávky/účasti/události; neměnnost záznamů. | P0 |

### 7.16 M16 — API a klienti (API)

| ID | Požadavek | Prio |
|---|---|---|
| API-01 | Doménové operace dostupné přes dokumentované API tak, aby web, odbavovací klient a případné další klienty používaly stejná pravidla. Zadání nepředepisuje styl API. | P0 |
| API-02 | Příjem platebních událostí z Pairrotu (webhook/polling) s ověřením původu a idempotencí. | P0 |
| API-03 | Veřejné čtení události (stránka, program, dostupnost) bez autentizace; registrace přes veřejný endpoint s ochranou proti zneužití (rate limit, antispam). | P0 |
| API-04 | Tokenová autentizace samoobsluhy účastníka s omezeným rozsahem. | P0 |
| API-05 | Export dat ve standardních formátech (CSV, JSON). | P1 |

---

## 8. Nefunkční požadavky

| Oblast | Požadavek |
|---|---|
| Použitelnost | Veřejná registrace plně použitelná na mobilu; administrace zvládnutelná bez technických znalostí; pokročilé volby progresivně odhalované. |
| Multitenance | Striktní oddělení dat organizátorů; každý dotaz a operace jsou tenant-scoped. |
| Autorizace | Kontrola oprávnění při každé citlivé operaci na serveru; odbavovací role nevidí plné osobní údaje. |
| Konzistence | Kapacity nelze překročit souběžnými operacemi; odbavení a platební události jsou idempotentní; finanční stav je odvozený z neměnných událostí. |
| Spolehlivost | Výpadek externí služby (Pairrot, e-mail, úložiště) nesmí ztratit přijatou registraci; odložené operace se opakují s dohledatelným stavem. |
| Historičnost | Odpovědi, potvrzení a dokumenty se interpretují ve verzi, ve které vznikly; historie se nepřepisuje. |
| Auditovatelnost | Viz AUD; auditní záznamy jsou neměnné. |
| Bezpečnost | Tokeny samoobsluhy a QR jsou nepředvídatelné, podepsané, odvolatelné; ochrana veřejných formulářů proti spamu; šifrování citlivých konfigurací (Pairrot credentials). |
| Ochrana údajů | Minimalizace, retenční třídy, anonymizace, varování u zvláštních kategorií; logy neobsahují osobní údaje nad nezbytnou míru. |
| Srozumitelnost chyb | Neúspěšné operace vysvětlují příčinu a nabízejí další krok (např. „místo bylo mezitím obsazeno — zapsat na čekací listinu?“). |
| Testovatelnost | Klíčové procesy (kap. 11) pokryté automatizovanými scénáři; testovací režim Pairrotu pro demo. |
| Lokalizace | Čeština, CZK; připraveno na další jazyk (P2). |
| Výkon | Veřejná stránka a registrační průchod rychlé i při nárazovém otevření registrací (stovky souběžných uživatelů). |

---

## 9. Integrační body

| Integrace | Směr | Role v systému | Poznámka |
|---|---|---|---|
| **Pairrot** | obousměrně | vytvoření platebního požadavku; příjem platebních událostí; (volitelně) stav požadavku | existující služba, appka neduplikuje párování; ověřit kontrakt (kap. 7.7) |
| **E-mailová služba** | ven | transakční i hromadné zprávy, stav doručení | reply-to organizátora, odhlášení u marketingu |
| **Úložiště souborů** | ven | fotografie, PDF dokumenty, materiály | neměnné kopie verzí dokumentů |
| **Mapy** | odkaz | místo konání | pouze odkaz, žádné API v P0 |
| **QR** | generování | platební QR (dle Pairrot/standardu bankovního QR), QR účasti, QR stránky události | |
| **Externí galerie** | odkaz | pouze správa odkazů a metadat | nelze garantovat odstranění fotek u třetí strany |

---

## 10. Výslovně mimo rozsah

Profesionální časomíra a výpočet výsledků · fyzické turnikety · nativní mobilní aplikace · vlastní bankovní párování · platební karty a brána · automatické bankovní refundace · účetní/fakturační systém · obecný právní poradce · CRM a pokročilý marketing · rozpoznávání osob na fotografiích · obecný rule engine · marketplace a provize · prodej konkrétních sedadel · offline synchronizace (P2) · e-shop se skladem a dopravou.

Tyto oblasti nesmí být implicitní závislostí základních funkcí.

---

## 11. Akceptační scénáře mimo CRUD

| # | Situace | Požadované chování | Co prokazuje |
|---|---|---|---|
| S01 | Dva lidé se registrují na poslední místo ve stejný okamžik | Místo získá právě jeden; druhý dostane srozumitelnou chybu a nabídku čekací listiny | konzistence, souběh |
| S02 | Objednávka obsahuje účast v aktivitě s volnou kapacitou i v aktivitě plné | Celá objednávka se buď vytvoří, nebo ne; žádné částečně obsazené místo | atomicita napříč úrovněmi kapacit |
| S03 | Pairrot doručí stejnou platbu třikrát | Zaúčtováno jednou, jedno potvrzení | idempotence |
| S04 | Účastník pošle jen část částky | Objednávka částečně uhrazená, účasti zůstanou v rezervaci, odejde upomínka na doplatek | doménová pravidla, rekonciliace |
| S05 | Platba přijde po expiraci rezervace, kapacita plná | Účast se neobnoví, vznikne výjimka s refund úkolem | stavové přechody, výjimky |
| S06 | Platba přijde po expiraci, kapacita volná, pořadatel zapnul auto-obnovu | Účast obnovena, potvrzení odesláno, auditováno jako systémová operace | konfigurovatelné pravidlo |
| S07 | Přeplatek | Finanční stav „přeplacená“, výjimka, refund úkol dle nastavení | odvozený stav |
| S08 | Pairrot nedostupný při registraci | Registrace uložena, platební požadavek vytvořen po obnovení, účastník informován | spolehlivost, asynchronní procesy |
| S09 | E-mailová služba nedostupná | Zprávy ve frontě, pozdější odeslání bez duplicit | outbox / retry |
| S10 | Dvě obsluhy odbaví stejný QR kód | Jeden platný záznam, druhá vidí, kdo a kdy | souběh, integrita |
| S11 | Obsluha opraví hotovostní úhradu | Obě operace v historii, finanční stav přepočítán | audit, neměnnost |
| S12 | Pořadatel změní čas startu nedělního závodu | Notifikace jen účastníkům nedělní aktivity | reakce na doménové události, cílení |
| S13 | Rodič přihlásí sebe na sobotu a dvě děti na neděli | 1 objednávka, 3 osoby, 3 účasti, potvrzení zákonného zástupce jen u dětí, jeden platební požadavek | kompozice objednávky, právní vazby |
| S14 | Pořadatel vydá novou verzi GDPR dokumentu bez požadavku na nové potvrzení | Staré registrace zůstanou vázané na starou verzi; nové používají novou | verzování |
| S15 | Nová verze podmínek **s** požadavkem na nové potvrzení | Dotčené účasti `vyžaduje řešení`, výzva odeslána, mlčení ≠ souhlas, přehled kdo potvrdil | nové rozhodnutí, stav |
| S16 | Účastník odvolá svolení k použití fotografií | Stav souhlasu změněn, pořadatel upozorněn, vznikne úkol prověřit publikované fotografie; registrace trvá | odvolání bez vedlejších efektů |
| S17 | Pořadatel přidá povinné pole po 50 registracích | Starší záznamy konzistentní (verze formuláře), výzva k doplnění, nic zdánlivě vyplněného | verzování formuláře |
| S18 | Účastník přejde z 5 km na 10 km | Ověří se kapacita 10 km, věkové podmínky, rozdíl ceny → nedoplatek/přeplatek, uvolnění místa 5 km, notifikace | změny s dopadem na kapacitu i finance |

Doplňkové (P1): převod účasti na jinou osobu vyžaduje nová potvrzení; čekací listina nabídne místo s limitem a po vypršení dalšímu; upomínka neodejde po úhradě/stornu (negativní test); zrušení akce vytvoří refund úkoly jen pro zaplacené objednávky.

---

## 12. Prioritizace

### P0 — Demonstrační produkt (funkční, ne prototyp)

- ORG-01, 02, 03, 06
- EVT-01–06, 10, 11, 12 (lite)
- PUB-01–04, 06
- FRM-01–09, 11
- REG-01–03, 05–08, 09 (storno + oprava údajů), 11, 14
- CAP-01 (bez vln) –06
- PAY-01–11, 13
- LEG-01–11
- PRV-07
- PRG-01–04
- COM-01–08
- CHK-01–10
- POST-01, 02, 03 (bez „jen registrovaní“), 04, 05, 07
- ADM-01–04, 06 (zpráva), 07, 08
- AUD-01–04
- API-01–04

### P1 — Pokročilá konfigurovatelnost a samoobsluha

ORG-04, 05 · EVT-07, 08, 09, 15 · PUB-05, 07 · FRM-10 · REG-04, 09 (plná), 10, 12, 13 · CAP-07 · PAY-12 · LEG-12, 13 · PRV-01–05 · ADM-05, 06 · API-05 · CHK-11 · POST-03 (jen registrovaní), 06 · COM-09

### P2 — Produktový růst

ORG-07 · EVT-13, 14 · PRG-05 · PRV-06 · CHK-12 · COM-10 · ADM-09 · lokalizace

---

## 13. Demonstrační prostředí

**Ukázková událost:** „Borský podzimní víkend 2026“ (fiktivní) · 21.–22. 11. 2026 · Plzeň, Borský park

| Den | Aktivita | Varianty | Kapacita | Specifika |
|---|---|---|---|---|
| Sobota | Trail 5 km | Jednotlivec 300 Kč | 100 | věkové kategorie |
| Sobota | Trail 10 km | Jednotlivec 400 Kč | 150 | min. věk 15, prohlášení o zdravotní způsobilosti |
| Sobota | Týmová štafeta | Tým 4 os. 1 200 Kč | 20 týmů | název týmu, členové |
| Neděle | Dětský závod | Dítě 100 Kč | 80 | zákonný zástupce, samostatné svolení k fotografiím dětí |
| Neděle | Canicross | Jednotlivec 350 Kč | 40 | pole „jméno psa“, očkovací průkaz (prohlášení) |

Doplňky: tričko (velikost), oběd. Platby: převod (Pairrot), hotovost na místě. Program po dnech, aktuality.

**Storyboard dema (jeden kompletní průchod + tři odbočky):**

1. Pořadatel založí událost průvodcem, nastaví aktivity, dokumenty (organizátor + aktivita), publikuje, zkopíruje odkaz.
2. Účastník A: rodič — sebe na Trail 10 km, dvě děti na Dětský závod, tričko; potvrdí jen relevantní dokumenty; zvolí převod. Dostane platební údaje a odkaz na samoobsluhu.
3. Pairrot potvrdí platbu → objednávka uhrazená → účasti potvrzené → e-mail.
4. Pořadatel pošle pokyny jen sobotním účastníkům; změní čas startu dětského závodu → notifikace jen nedělním.
5. Den akce: obsluha odbaví rodiče (QR), vidí, že děti jsou na jinou aktivitu/den; pokus o druhé odbavení ukáže „již odbaven“.
6. Po akci: galerie + externí odkaz, závěrečná zpráva odbaveným, report, dokončení.

Odbočky: účastník B platí hotově (potvrzen ihned, hotovost při odbavení); účastník C pošle méně (částečná úhrada, upomínka); Pairrot doručí duplicitní platbu (nic se nestane podruhé); účastník A odvolá svolení k fotkám dětí (úkol pořadateli); pořadatel vydá novou verzi podmínek s požadavkem na nové potvrzení.

---

## 14. Otevřené otázky pro solution architecture

Zadání je záměrně nenařizuje; architektura je musí rozhodnout a zdokumentovat.

1. **Hranice modulů / bounded contexts** — návrh: Organizace & identita · Katalog událostí (struktura, formulář, dokumenty) · Registrace & kapacity · Platby & rekonciliace · Právní potvrzení & souhlasy · Komunikace · Odbavení · Obsah po akci · Reporting · Audit. Které sdílí transakci (registrace + kapacita + potvrzení) a které komunikují asynchronně (platby, komunikace, audit)?
2. **Konzistence kapacit** — zamykání vs. optimistické verze vs. counter s rezervacemi; jak řešit atomicitu napříč úrovněmi (událost/aktivita/varianta/vlna) v jedné objednávce.
3. **Idempotence** — ukládání externích ID (Pairrot platba), idempotenční klíče pro odbavení a odeslání zpráv, deduplikace webhooků.
4. **Asynchronní procesy** — outbox pattern pro notifikace a platební požadavky; plánovač expirací rezervací a upomínek; retry politika; dead-letter → fronta výjimek.
5. **Audit vs. event sourcing** — stačí append-only audit log + odvozené finanční stavy z platebních událostí, nebo event sourcing pro objednávku/účast?
6. **Schéma dynamického formuláře** — uložení verze schématu (snapshot), uložení odpovědí (typované vs. JSON), indexace/filtrování podle vlastních polí, validace z jednoho zdroje pravdy (server) a odvození UI.
7. **Verzování dokumentů** — neměnná kopie obsahu (hash + úložiště), odkaz externího dokumentu jako snímek.
8. **Tokeny** — samoobsluha (životnost, rotace, odvolání) a QR účasti (podpis, co nese, co se stane při zrušení účasti).
9. **Multitenance** — tenant-scoping na úrovni dat a autorizace; přístup jednoho účtu k více organizátorům.
10. **Oprávnění** — model rolí × událostí; redukované projekce osobních údajů pro odbavovací roli.
11. **Soubory** — úložiště fotografií a PDF, náhledy, přístupová pravidla pro „jen registrovaní“.
12. **E-mail** — poskytovatel, odesílací doména platformy, reply-to organizátora, zpracování bounce/stavů, odhlášení.
13. **Pairrot** — potvrzení skutečného kontraktu (viz kap. 7.7), testovací režim, ověření původu webhooků, zpracování nespárovaných plateb.
14. **Retence** — plánovaná anonymizace, oddělení finančních záznamů, dopad na reporty a audit.
15. **API pro klienty** — veřejná vs. administrační vs. odbavovací část, autentizace, rate limiting veřejných endpointů, připravenost pro budoucí offline odbavení.
16. **Testovací strategie** — scénáře S01–S18 jako automatizované testy nad doménou; simulace Pairrotu a e-mailu.
