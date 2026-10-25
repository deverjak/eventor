---
status: accepted
last-verified: 2026-10-24
---

# ADR 010 — Monorepo with an API-first Symfony backend and a Next.js frontend on the same site

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be the repository layout `backend/`, `frontend/`, `docs/`, `compose.yaml`, the `frontend/next.config.ts` rewrite of `/api/*`, and the CI job that regenerates the OpenAPI types.

## Context

- **API for several clients (API-01).** The same domain rules must serve the web, a check-in client and future clients.
- **Mobile-first public flow (PUB-04, NFR "Použitelnost").** The public page and registration must work fully on a mobile, with accessible forms.
- **Fast public page (NFR "Výkon").** It must stay fast under a registration rush.
- **Rich admin.** The admin side is extensive: a form editor with conditions (FRM), dynamic tables (ADM-04, ADM-05) and a camera-based check-in screen (CHK-01, CHK-02).
- **Authentication.** Session cookies for the team ([ADR 006](006-authentication-session-tokens-no-jwt.md)) require the same site.
- **The author** knows Next.js well from main-panel. The interview evaluates Symfony depth, so the backend must stand on its own as an API.
- **Local development on Windows** runs only in Docker.

## Decision

**Repository layout.**

```
eventor/
  backend/      Symfony 8 (PHP 8.4, FrankenPHP), API only, no Twig pages (Twig only for emails)
  frontend/     Next.js 16 App Router, TypeScript, typed client generated from OpenAPI
  docs/         architecture book, ADRs, assignment
  compose.yaml  php, worker, frontend, postgres, smtp4dev, minio (+ pairrot-sandbox from its own repo/image)
  Makefile      up, down, test, qa, openapi, docs
```

**Same site.**
- Locally, Next.js on `:3000` rewrites `/api/:path*` to `http://php/api/:path*`, so the browser talks to one origin.
- In production, Caddy in the FrankenPHP image routes `/api/*` to PHP and everything else to the Next.js server. Both sit behind one domain, which keeps the cookies first-party and CORS out of the picture.

**Rendering.**
- Public event pages are server-rendered with short revalidation, fed by public `GET` endpoints that send cache headers. Registration is a client-side flow against the public command endpoint.
- Admin is client-heavy with server components for the shell.
- Check-in is a dedicated mobile route with camera QR scanning (CHK-01, CHK-02), working as a PWA so it can grow into offline support (P2).

**Contract.** `npm run openapi` turns the backend's `/api/doc.json` into `frontend/src/api/schema.d.ts` through `openapi-typescript`, and `openapi-fetch` uses the generated types. CI fails on a stale schema. The frontend implements no business rule. It mirrors validation only for UX, using the schema-driven form ([ADR 007](007-dynamic-form-schema-jsonb-snapshots.md)).

**Ownership.** The backend owns all logic, authorisation and validation. The frontend can be replaced without touching domain code.

## Consequences

**Positive**
- The API is complete by construction because the UI uses nothing else.
- Atomic changes across API and UI happen in one pull request.
- Strong UX for the form editor and check-in.

**Negative**
- Two runtimes (PHP and Node) and two build pipelines.
- Same-site deployment is a hard constraint for the admin session.
- The Next.js server adds a hop for server-rendered pages. That is acceptable, and the public `GET`s are cacheable.

## Alternatives considered

- **Twig + Symfony UX (Turbo, Live Components) for everything.** A single stack that shows Symfony on the front end too. But API-01 would have to be built separately for the check-in and other clients, and a rich conditional form editor in Live Components is laborious.
- **Hybrid** (public pages and check-in in Twig + UX, admin as a SPA). Two front-end technologies to maintain, with no clear gain over one.
- **Vite + React SPA without SSR.** Simpler hosting, but the public pages lose server rendering and the performance it brings.
- **Two repositories.** Contract drift and cross-repo pull requests for every API change.

## Verification

- A CI job `openapi:check`: regenerate the types, then `git diff --exit-code`.
- A Playwright end-to-end run of the storyboard (assignment chapter 13) against the full compose stack.
- A Lighthouse budget for the public event page on mobile.

## Related

[ADR 006](006-authentication-session-tokens-no-jwt.md), [ADR 008](008-command-oriented-http-api.md) · [01 Context and deployment](../architecture/01-kontext-a-nasazeni/README.md) · Supersedes: None.
