---
status: accepted
last-verified: 2026-10-24
---

# ADR 006 — Authentication: session cookie for the team, purpose-bound tokens for participants and devices, no JWT

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/config/packages/security.yaml` (firewalls `webhook`, `self_service`, `check_in`, `admin`), `SelfServiceTokenAuthenticator`, `DeviceAccessTokenHandler`, `PairrotWebhookAuthenticator`, and `tests/Integration/Security/*`.

## Context

Eventor has four kinds of caller with different trust and lifetimes:

| Caller | Requirement |
|---|---|
| Team member in the Next.js admin | long session, revocable, CSRF-safe, multiple organizers (ORG-03, ORG-04) |
| Participant / orderer without an account | access through a link from email, scope limited to one order, unpredictable, revocable, recoverable without revealing whether an email exists (REG-02, REG-08, REG-11, API-04, NFR "Bezpečnost") |
| Check-in device (phone/tablet) | works for a whole event day, is revocable, prepares for offline check-in (CHK-01, CHK-12 P2) |
| Pairrot | proves the origin of its webhooks (API-02) |

The frontend is Next.js on **the same site** as the API: `/api/*` is proxied by a Next.js rewrite locally and by Caddy in production ([ADR 010](010-monorepo-nextjs-frontend.md)). Both the Symfony and the API Platform docs recommend a cookie session over JWT for a first-party SPA on the same site.

## Decision

Four firewalls in `security.yaml`, in this order:

1. **`webhook`** (`^/api/webhooks`, stateless). `PairrotWebhookAuthenticator` verifies the HMAC signature ([ADR 004](004-pairrot-integration-contract.md)) and authenticates a technical `PairrotUser`. Anything unsigned gets 401.
2. **`self_service`** (`^/api/self-service`, stateless). `SelfServiceTokenAuthenticator` reads the `Authorization: SelfService ss1_<id>_<secret>` header, or the token in the path from a link. The frontend exchanges the link token into the header and never logs it.
   - The `id` looks up `self_service_token`. The secret is compared as SHA-256 in constant time.
   - It checks `expires_at` (default: the end of the event + 30 days) and `revoked_at`, then authenticates `ParticipantPrincipal(orderId, scopes)`.
   - Scopes cover viewing, cancelling, editing data and withdrawing consent, following the event rules (REG-09).
   - Recovery (REG-11) always answers `202` and sends a new token only if the email matches an order. A `RateLimiter` is applied per IP and per email hash.
   - The organizer can revoke tokens. A transfer (REG-10) revokes the old person's tokens.
3. **`check_in`** (`^/api/check-in`, stateless). The built-in `access_token` authenticator uses the custom `DeviceAccessTokenHandler`.
   - Tokens `dev1_<id>_<secret>` are opaque, stored hashed in `device_token(organizer_id, event_id, station_id?, created_by, expires_at, revoked_at)`, and issued by a manager as a QR to scan on the device.
   - The device is the principal and the acting staff member is recorded on each check-in.
   - A logged-in team member with `CHECK_IN` may also use the admin session, so the `check_in` firewall shares the `admin` context.
4. **`admin`** (`^/api`, stateful). `json_login` at `/api/auth/login` issues an HttpOnly, `Secure`, `SameSite=Lax` session cookie.
   - Logout is the built-in `logout`. The session store is `PdoSessionHandler` in Postgres, so sessions survive worker restarts and can be revoked.
   - CSRF protection uses `SameSite` plus a stateless CSRF token header on unsafe methods (Symfony `csrf_protection` with the stateless `SameOriginCsrfTokenManager`).
   - Passwords are hashed with `auto` (argon2id). Invitations (ORG-03) are signed, expiring links (`UriSigner`) that set the password.
   - Login and password-reset endpoints are rate-limited.

**Participant QR tokens are not authentication.** They identify a participation for check-in ([08](../architecture/08-prurezove-koncepty/README.md)) and are verified by the check-in endpoint after the device has authenticated.

**No JWT anywhere.**

## Consequences

**Positive**
- Every caller is revocable server-side.
- No token ever sits in browser `localStorage`.
- Each mechanism matches its threat model.
- It demonstrates three Symfony authentication extension points: a custom authenticator, an access token handler and `json_login`.

**Negative**
- Same-site deployment is required for the admin session (accepted, see ADR 010).
- The device and self-service token tables need cleanup, handled by a Scheduler purge of expired rows.

**Security**
- All token secrets are 256-bit and stored as SHA-256 hashes, and token prefixes make leaked tokens greppable.
- Monolog `PiiRedactionProcessor` masks `ss1_`, `dev1_` and `qr1.` patterns.

## Alternatives considered

- **JWT (LexikJWTAuthenticationBundle) for the team.** Hard to revoke and tempts storage in `localStorage`, with nothing gained on a same-site SPA.
- **Stateless signed links (`UriSigner`) for self-service.** Cannot be revoked individually without a store. Since a store is needed anyway, opaque tokens are simpler.
- **Participant accounts.** Explicitly out of scope (REG-02).
- **Session login on check-in devices.** Ties devices to personal accounts and does not prepare for offline use.

## Verification

- `SelfServiceTokenAuthenticatorTest`: a valid token, an expired token, a revoked token, a wrong secret (constant time), and a scope mismatch giving 403.
- `RecoveryDoesNotLeakTest`: identical responses and timing envelope for known and unknown emails.
- `CsrfTest`: an unsafe admin request without the token header gets 403.
- `WebhookAuthenticatorTest`: Pairrot's test vector, a rotation with two signatures, and a skew of more than 5 minutes.

## Related

[ADR 004](004-pairrot-integration-contract.md), [ADR 005](005-tenant-from-url-doctrine-filter-voters.md), [ADR 010](010-monorepo-nextjs-frontend.md) · [08 Crosscutting concepts](../architecture/08-prurezove-koncepty/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

Invitation tokens are HMAC-derived and only SHA-256 is stored; `acceptToken` is returned only in dev/test. The check-in firewall matches both a `Bearer dev1_` token and the request path, otherwise it falls through to the team firewall. Event scope is read from the database by `AccessPolicy::isAllowedForEvent`/`eventScope`; a second revoke returns 404. Sign-up and invitation routes share a production limit of 20/h.
