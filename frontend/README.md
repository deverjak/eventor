# Eventor · aplikace pořadatele

Next.js App Router, TypeScript, Tailwind CSS 4. Aplikace nemá marketingovou landing page. `/` otevírá ukázkový pracovní prostor `/org/demo/overview`.

## Spuštění

Z kořene repozitáře:

```sh
docker compose up -d --no-deps frontend
```

Aplikace: http://localhost:3000. Kontejner používá Node 24, `npm ci` a `npm run dev`. Závislosti a `.next` mají vlastní Docker volumes. Backendové služby se tímto příkazem nerestartují. `BACKEND_ORIGIN=http://php` a `OPENAPI_URL=http://php/api/doc.json` platí uvnitř Compose.

Pro lokální nástroje je podporován Node >=22.18. `npm ci`, `npm run dev`; backend je výchozí `http://localhost:8080`. Nastavení lze změnit v `.env.local` podle `.env.example`. Po změně `BACKEND_ORIGIN` restartujte Next server.

## Struktura a rozpracování

- `src/components`: sdílený design, responzivní navigace a session gate.
- `src/app/login`, `src/app/organizers`: přihlášení session cookie, seznam členství, přepnutí organizátora a odhlášení.
- `src/app/org/[orgSlug]`: přehled, katalog, detail akce, registrace, platby, komunikace, odbavení, dokumenty, tým a nastavení.
- `src/features`: obrazovky a oddělená ukázková data. Vyhledávání a filtrování katalogu funguje; formulář nové jednoduché akce používá existující command endpoint. Detail odděluje program, formulář, kapacity, platby a dokumenty.
- `src/api`: ručně ověřené DTO a transport podle současných PHP kontrolerů. `schema.d.ts` vzniká výhradně exportem backendu.

`demo` je zatím vyhrazený slug pro ukázku návrhu. Ukázka neodesílá zápisy do backendu a není náhradou přihlášení. Ostatní prostory vyžadují backendovou session a členství; skutečnou autorizaci vždy vynucuje backend. Žádné přihlašovací tokeny nejsou v localStorage. Nedostupný backend zobrazí chybu, nikoli ukázková data.

Registrace, komunikace a čtečka QR jsou připravené navigační a vizuální prostory; funkce vyžadují dokončení příslušných HTTP endpointů. Editor formulářů, editace programu, publikace, správa práv a finanční příkazy jsou další implementační kroky. Nejde o dokončený frontend W5 ani o produkční vydání.

## API kontrakt a kontrola

Po spuštění backendu:

```sh
docker compose exec frontend npm run openapi
docker compose exec frontend npm run openapi:check
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run lint
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

`openapi:check` ověřuje shodu generovaných typů s aktuálním exportem. Backendové OpenAPI popisy zatím nemusejí obsahovat kompletní DTO, proto se současné obrazovky opírají o ručně ověřené typy. Generování nesmí přepsat kontrakt chybnou HTML odpovědí. API klient přidává cookie credentials, `csrf-token`, volitelný `If-Match` a idempotency key; chyby zachovávají Problem Details a correlation ID. Zápisy se automaticky neopakují.

Ověření bootstrapu: produkční build, TypeScript a celý ESLint prošly na hostu; všech 7 transportních testů prošlo i v Dockeru. Prohlížečem ověřen přehled na 1440 px a 390 px, mobilní navigace, hledání a dialog nové akce. Mobilní přehled nepřetéká do šířky. OpenAPI export během práce končil timeoutem backendu. ESLint v Dockeru na tomto Windows hostu opakovaně hlásil `EIO` při asynchronním čtení souborů z bind mountu; stejná kontrola na hostu prošla. Pokud hot reload načítá starou verzi souboru, restartujte frontend kontejner.

## Navazující práce

1. Po stabilizaci W0–W3 regenerovat OpenAPI, ověřit DTO a připojit endpointy registrací, komunikace a odbavení.
2. Doplnit editory aktivit, variant, formulářů, právních požadavků a platebních pravidel; zachovat backendové verze a oprávnění.
3. Doplnit command akce, validace polí z Problem Details a end-to-end scénáře proti skutečnému backendu.
4. Veřejné registrační a samoobslužné flow řešit odděleně od administrace; marketingový web není součástí této etapy.

Architektura vychází z [ADR 010](../docs/adr/010-monorepo-nextjs-frontend.md), [ADR 006](../docs/adr/006-authentication-session-tokens-no-jwt.md) a [ADR 008](../docs/adr/008-command-oriented-http-api.md). Bootstrap ověřen podle [Next.js installation](https://nextjs.org/docs/app/getting-started/installation) a [Tailwind Next.js guide](https://tailwindcss.com/docs/installation/framework-guides/nextjs).
