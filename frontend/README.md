# Eventor — aplikace pořadatele

Next.js App Router, TypeScript a Tailwind CSS 4. `/` zobrazuje přihlášení s odkazem na registraci. Stejný přihlašovací formulář je i na `/login`; vlastní organizátoři jsou na `/organizers`. Registrace na `/register` založí účet i organizátora. Adresa organizátora se generuje z názvu bez ručního pole; při potvrzené kolizi doplní náhodný suffix. Slug akce generuje přímo backend. Ukázková organizace, ukázková data i výjimka z přihlášení byly odstraněny. Existující skutečný organizátor se slugem `demo` podléhá stejnému přihlášení jako ostatní.

## Spuštění

Z kořene repozitáře: `docker compose up -d --no-deps frontend`. Aplikace běží na http://localhost:3000. Při lokálním spuštění použijte Node >=22.18, `npm ci` a `npm run dev`. Backend je výchozí `http://localhost:8080`; uvnitř Compose se používá `BACKEND_ORIGIN=http://php`. Nastavení je v `.env.example`.

## Funkce

- Účet: registrace s vlastním organizátorem, přihlášení session cookie, odhlášení, výběr členství, přijetí týmové pozvánky včetně e-mailové cesty `/invitations/accept`.
- Organizátor: profil, platební pravidla a politiky, připojení bankovního účtu přes Pairrot, členové, role a rozsah akcí, pozvánky a odbavovací zařízení.
- Akce: vytvoření jednoduché nebo víceaktivitové akce, úpravy, publikace, dokončení, archivace a storno; otevření, pozastavení, obnovení a uzavření registrací.
- Katalog: aktivity, varianty, ceny, kapacity, program, aktuality, verzovaný formulář s typy, omezeními a podmínkami; platební pravidla.
- Registrace: účastníci, hledání a stránkování, detail objednávky, storno a obnova účasti. Z účastníka lze přímo otevřít správu jeho objednávky.
- Finance: účetní kniha, hotovost, ruční platba, oprava hotovosti, nespárované platby a kandidáti, přiřazení platby, vratky, opakování platebního požadavku.
- Komunikace: šablony, náhled a proměnné, kampaně, plánování a odeslání, zrušení, doručení a opakování, blokování adres a připomínky. Editace kampaně načítá její úplný detail před uložením.
- Odbavení: výběr akce a seznamu, hledání, vložení QR obsahu nebo registračního kódu, odbavení, potvrzení varování, hotovost, eskalace a opravy. Kamera zatím není součástí čtečky.
- Legal: dokumenty a jejich verze, text, nahrání PDF nebo externí snímek, publikace a opětovné potvrzení, právní požadavky a historie potvrzení.
- Content W4: galerie a externí kolekce, viditelnost, multipart upload JPEG/PNG/WebP, publikace a stažení, archivace, materiály a příprava závěrečné kampaně.
- Přehled: fronta Attention a její příkazy; auditní historie.
- Veřejná část: `/events/[orgSlug]/[eventSlug]`, výběr účastníků a variant, formulář z API, právní potvrzení, odeslání objednávky, veřejné fotografie a materiály. `/self-service` a e-mailová cesta `/s/[token]` zpřístupní vlastní objednávku, platební pokyny, opravy, storno, chybějící potvrzení a obnovení přístupového odkazu.

Reporting není součástí dokončeného Content W4 a nemá zde implementovanou obrazovku. Některé pokročilé operace nad souvisejícími záznamy zatím přijímají jejich ID (například oprava konkrétní účetní položky nebo párování transakce). QR a SPAYD řetězce jsou dostupné v detailu, grafické QR vykreslení zatím chybí. Oprávnění vždy ověřuje backend; frontend přizpůsobuje navigaci a akce roli. Podmíněná oprávnění, která API nevystavuje jako capabilities, frontend automaticky neodhaduje.

## API a ověření

Veškeré požadavky jdou přes same-origin `/api/*` rewrite. Session tokeny se neukládají do localStorage. Samoobslužný token se po otevření e-mailového odkazu odstraní z adresy a zůstává jen v paměti. Zápisy obsahují CSRF podle backendové politiky. Příkazy se automaticky neopakují; při ručním opakování stejného formuláře se zachová klíč idempotence. Editace katalogu předává `If-Match` a konflikt vyžaduje nové načtení. Multipart upload ponechává Content-Type a boundary prohlížeči.

```sh
npm run typecheck
npm run lint
npm test
npm run build
npm run openapi
npm run openapi:check
```

`schema.d.ts` je skutečný export běžícího API. Protože část OpenAPI popisuje odpovědi pouze textově, ručně ověřené DTO zůstávají v `src/api/types.ts`. Formuláře a cesty jsou odvozené ze skutečných backendových controllerů a request DTO.

Volitelný skutečný integrační test: `node scripts/workspace-smoke.mjs`. Vytvoří vlastní organizaci s prefixem `frontend-smoke-` a testovací data; nespouští se v `npm test`. Výchozí server je http://localhost:3000, lze změnit přes `SMOKE_ORIGIN`. Ověřuje API přes stejný frontendový proxy průchod jako aplikace, včetně session, publikace, obsahu a uploadu, komunikace, týmu, dokumentů, objednávky a financí. Testovací organizace zůstává v lokální DB pro kontrolu; test nepřepisuje existující organizátory.
