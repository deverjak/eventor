# Frontend API guide

This is the operational companion to [contracts.md](contracts.md). Generate the TypeScript client from `/api/doc.json`; this guide records conventions and workflow rules that are easy to miss in OpenAPI. Paths and behaviour below are derived from `backend/src/*/UserInterface/Http`, security configuration, and Shared HTTP code. Registration, Communication, and Check-in routes are **(W3, in progress)** because those modules are uncommitted in this working tree.

## 1. Zones and authentication

| Zone | Authentication | Use it for |
|---|---|---|
| Public | none | `/api/public/...`, sign-up, invitation preview/accept, health and documentation |
| Admin team | Symfony session cookie | `/api/admin/org/{orgSlug}/...`, `/api/auth/me` |
| Participant self-service | `Authorization: SelfService ss1_…` | `/api/self-service/...` **(W3, in progress)** |
| Check-in | team session or `Authorization: Bearer dev1_…` | `/api/check-in/...` **(W3, in progress)** |
| Webhooks | Pairrot HMAC | `/api/webhooks/pairrot`; never call from the frontend |

### Team session

`POST /api/auth/login` accepts `email` and `password`, sets the session cookie, and returns the same profile as `GET /api/auth/me`. `POST /api/auth/logout` ends it. A session expires after eight hours of inactivity and has a non-renewable **7-day** lifetime from sign-in; a later request becomes anonymous and protected routes return 401 (`backend/src/Identity/Infrastructure/Security/AbsoluteSessionLimitListener.php`). On app boot, call `/api/auth/me`; treat 401 as signed out.

Cookie calls must use credentials (for example `credentials: 'include'`). Every unsafe request (`POST`, `PUT`, `PATCH`, `DELETE`) whose path starts `/api/admin` **or `/api/auth`** must send:

- `csrf-token`: a fresh client-generated random value of at least 24 characters (a UUID is suitable). It need not be fetched from the server.
- a same-origin request. Browser requests should include `Origin: https://app.example` automatically; it must match the API origin. Cross-site Origin/Referer/Sec-Fetch-Site is rejected. There is no CORS escape hatch.

This includes login, logout, sign-up and invitation actions, even though some are public. It does **not** apply to public orders, self-service tokens, device tokens, or webhooks (`backend/src/Shared/UserInterface/Http/CsrfGuardListener.php`, `backend/config/packages/framework.php`).

```ts
await fetch('/api/admin/org/acme/events', {
  method: 'POST', credentials: 'include',
  headers: { 'content-type': 'application/json', 'csrf-token': crypto.randomUUID() },
  body: JSON.stringify(payload),
}); // browser supplies the same-origin Origin header
```

Participant links are delivered after registration (and can be resent); the frontend takes the token from the link and uses it only as the `SelfService` Authorization credential. The token is bound to one order and must never be put in a subsequent URL, log, or response. `POST /api/public/self-service/recover` **(W3, in progress)** always returns `202 {"status":"accepted"}`, even for malformed, unknown, or throttled requests, to prevent account enumeration (`SelfServiceRecoverController.php`).

For a kiosk, issue a device token in the admin UI and store `dev1_…` only on that device. Call `GET /api/check-in/whoami` first: it identifies `device` (including its optional list restriction) or `member`. Check-in team routes include the organization in their path; device routes omit it because the token supplies the tenant (`CheckInRoutes.php`).

## 2. Tenancy and permissions

Admin resources live below `/api/admin/org/{orgSlug}`. Do not substitute an organization id for the slug. Resolution order is intentional: **401** no session → **404** unknown organizer or a session user who is not a member of that organizer → **403** member lacks the declared permission (`RequiresPermissionListener.php`). A 404 must not be presented as “request access”, because it deliberately hides foreign organizers.

### Role capability matrix

`/api/auth/me` and organizer detail expose membership/role; use them to hide navigation and buttons, but always handle the server 403. This is the code matrix in `Identity/Application/Access/PermissionMatrix.php`.

| UI area / permission | OWNER | EVENT_MANAGER | FINANCE | CHECK_IN |
|---|---:|---:|---:|---:|
| Organizer settings, team, organizer legal | yes | — | — | — |
| Event/edit/configure/legal/program/content | yes | yes | — | — |
| Full participants / data export / registration edit | yes | yes | full only | check-in view only |
| Payments, cash, refunds | yes | create/cash/refund create | yes | policy cash only |
| Check-in | yes | yes | — | perform only |
| Communication | yes | yes | — | — |
| Audit | all | own events | finance | — |

`EVENT_MANAGER` gets `payment.exception.resolve` only when organizer policy enables it; `CHECK_IN` gets `cash.record` only when its policy enables it. Hide the affected action until a server-provided capability/policy is available; never assume the role alone grants it.

### Event-scoped members (ORG-03)

A membership restricted to events is authorized only when a route contains `{eventId}` and it is one of its events. Organizer-wide routes otherwise return 403, except these explicit `scopedAllowed` reads:

| Route | Why it remains available |
|---|---|
| `GET /api/admin/org/{orgSlug}` | organizer shell + own role |
| `GET /api/admin/org/{orgSlug}/events` | event picker (must be filtered to scope by its query) |
| `GET /api/admin/org/{orgSlug}/form-field-types` | static form-editor vocabulary |

So scoped members should see only their event workspace and event-scoped participant/check-in/catalog actions; hide team, settings, finance queues, communication, legal, audit, attention, and any organizer-wide menu. The exception mechanism is declared in `RequiresPermission.php`; the three uses above are the only `scopedAllowed: true` uses.

## 3. Errors

All `/api` errors are RFC 9457 `application/problem+json` (`ProblemDetailsListener.php`). Read `code`, not title/detail. The shape is:

```json
{"type":"https://eventor.cz/problems/capacity-exhausted","code":"capacity_exhausted","title":"…","detail":"…","context":{"bucketId":"…","scope":"variant","waitlistAvailable":true},"nextSteps":[{"action":"join_waitlist"}],"violations":[{"path":"persons.0.email","message":"…","code":"…"}],"correlationId":"…"}
```

Fields may be absent/empty. Put `violations` on matching form fields; show `detail` only as a fallback; include `correlationId` in support reports. Use `Retry-After` for 429. Relevant codes and UI reactions:

| Area | Important codes | UI reaction |
|---|---|---|
| Identity | `invalid_credentials`, `email_already_registered`, `organizer_slug_taken`, `csrf_invalid`, `rate_limited` | inline login/sign-up error; renew CSRF token and retry only after correcting it |
| Catalog | `event_slug_taken`, `invalid_catalog_data`, `form_schema_invalid`, `form_invalid`, `version_conflict`, `event_not_publishable`, `transition_not_allowed` | field errors; on stale version reload/merge before another edit |
| Registration (W3) | `capacity_exhausted`, `registration_closed`, `payment_method_not_allowed`, `missing_confirmation`, `not_eligible`, `spam_suspected` | capacity is 409: if `nextSteps` contains `join_waitlist`, offer waitlist; otherwise refresh availability. Map form violations. |
| Self-service (W3) | `token_invalid`, `self_service_action_not_allowed`, `participation_transition_not_allowed` | offer recovery from `nextSteps`; reload order after a rejected lifecycle action |
| Finance | `refund_exceeds_balance`, `refund_task_not_open`, `payment_request_state`, `allocation_not_possible`, `pairrot_unavailable` | retain entered data; show state conflict; `503` is retryable |
| Communication (W3) | `template_exists`, `template_invalid`, `campaign_state_invalid`, `delivery_not_retryable` | inline template/campaign errors; refresh state before resend |
| Check-in (W3) | `already_checked_in`, `check_in_blocked`, `check_in_warning_unacknowledged`, `check_in_list_closed` | show the returned verdict/required acknowledgement; do not silently retry |
| Attention | `attention_item_not_found`, `attention_action_not_available` | refresh queue/item and render only actions returned by the item |

The examples are real domain mappings, for example `CapacityExhausted.php` supplies `context.waitlistAvailable` and `nextSteps: [{action: "join_waitlist"}]`; `ProblemFactory.php` turns payload validation into 422 `validation_failed` with `violations`.

## 4. Idempotency

Generate a UUID with `crypto.randomUUID()` per user intent. Send it as `Idempotency-Key`; valid values are `[A-Za-z0-9_.:-]{16,128}`. Persist the key alongside the pending UI action and reuse the **same key and identical body** for a network retry. A successful duplicate returns the stored command result; a duplicate while work is active is `409 request_in_progress`; same key with changed input is `409 idempotency_key_reused`. Missing key is `400 idempotency_key_required`; malformed key is `422 invalid_idempotency_key` (`Shared/Application/Idempotency/*`, `IdempotencyMiddleware.php`).

| Required endpoint | Module |
|---|---|
| `POST /api/public/orgs/{orgSlug}/events/{eventSlug}/orders` | Registration **(W3, in progress)** |
| `POST /api/admin/org/{orgSlug}/orders/{orderId}/manual-payments` | Payments |
| `POST /api/admin/org/{orgSlug}/orders/{orderId}/cash` | Payments |
| `POST /api/admin/org/{orgSlug}/orders/{orderId}/ledger/{entryId}/correction` | Payments |
| `POST /api/check-in/.../lists/{listId}/check-ins` (team or device path) | Check-in **(W3, in progress)** |
| `POST /api/check-in/.../lists/{listId}/cash-check-ins` (team or device path) | Check-in **(W3, in progress)** |

## 5. Values and concurrency

- Money is `{ "minor": 30000, "currency": "CZK", "formatted": "300 Kč" }`; never use a float. `formatted` is display-only and ignored on input (`MoneyNormalizer.php`).
- Times are ISO 8601 date-times with an offset. Preserve the offset; do not reinterpret a local event time as UTC.
- IDs are RFC UUID v7 strings (`Shared/Domain/Id/AbstractId.php`), not opaque numeric IDs.
- Catalog event updates, payment rules, activity update and variant update accept optional `If-Match: <version>` (quoted/weak forms are accepted). Copy the `version` from a read. A stale write is `409 version_conflict`; omitting it is deliberately unconditional (`Catalog/UserInterface/Http/ExpectedVersion.php`).

## 6. Lists, pagination, and filters

Do not invent a common cursor protocol: lists are module-specific. In particular, admin participation list **(W3)** accepts `page` (minimum 1) and `limit` (capped at 100), plus optional `state`, `activityId`, and `q`; its response includes rows, `total`, `page`, and `limit` (`AdminParticipationQuery.php`). Attention uses `page`/`perPage` (default 25, max 100) plus `type`, `state`, and `eventId`, returning `items`, `page`, `perPage`, and `total` (`AttentionListController.php`). Communication delivery lists **(W3)** use their `ListQuery` query DTO; consume the generated operation parameters rather than applying the registration convention. Public event, event, team, capacity and several finance lists currently return their declared complete view rather than a shared pagination envelope.

## 7. Screen flows

All admin mutations in these flows require the session/CSRF rule above. Replace `{org}` etc. with encoded path values.

### Organizer onboarding (ORG-01/02)

1. `POST /api/auth/sign-up` with account and organizer fields; session begins and response is the profile.
2. `GET /api/auth/me`; select the returned organization slug.
3. `GET /api/admin/org/{org}` then profile/payment settings as needed; `PUT /profile` and `PUT /payment-settings` are owner-only.
4. Team management is `GET/POST /members`, `PUT/DELETE /members/{membershipId}`, and invitation list/revoke routes. Invitation preview/accept are public `/api/auth/invitations/*` calls.

### Event setup and publication (Catalog)

1. `POST /api/admin/org/{org}/events`; then read `GET /events/{eventId}` and retain `version`.
2. Add/update activities and variants under `/events/{eventId}/activities` and `/events/{eventId}/variants/{variantId}`. Configure limits with `POST /events/{eventId}/capacity`; inspect real occupancy via `GET .../capacity`.
3. Edit `PUT /events/{eventId}/form/draft`, preview with `POST .../form-preview`, then inspect/publish through `GET .../form` and `POST .../form/publish`.
4. Set payment rules with `PUT /payment-rules`, open registration via `POST /registration/open`, then `POST /publish`. Publish validates the complete event and freezes form version 1.

### Public event and registration (W3, in progress)

1. Load `GET /api/public/orgs/{org}/events/{slug}`, form, and availability endpoints.
2. Post the selected items to `POST .../registration-form` to receive the selection-specific schema/requirements.
3. Validate locally, then `POST .../orders` with `Idempotency-Key`, honeypot/form-token fields as specified by OpenAPI.
4. The 201 gives order identity, self-service URL, and `payment.status`, not bank data. Open the self-service order; render its frozen `spaydPayload` as QR—never recreate it—and show its bank instructions or `paymentInstructionsPending` (`PaymentInstructionsView.php`).

### Participant self-service (W3, in progress)

1. From the emailed/order link set `Authorization: SelfService ss1_…`; `GET /api/self-service/order`.
2. `POST /participations/{id}/cancel`, `PUT /persons/{id}`, or `PUT /contact`; every success returns the updated order.
3. `POST /confirmations` for legal decisions, then render the returned/open confirmation state.
4. For 401 `token_invalid`, offer public recovery; recovery always gives 202, never a “not found” result.

### Admin participant, restore, and attention (W3 registration)

1. `GET /api/admin/org/{org}/events/{eventId}/participations?page=1&limit=…` and drill into participation/order detail.
2. Cancel with `POST .../participations/{id}/cancel`; restore with `POST .../restore` only when policy/permission permits.
3. Finance/attention roles load `GET /attention/summary`, `GET /attention`, and an item. Render server-provided actions: POST only `kind: command`; navigate a `kind: link` through its owning module. `resolve`/`dismiss` additionally require `payment.exception.resolve`.

### Finance

1. From an order open `GET /orders/{orderId}/finance` and `/ledger`.
2. Record manual payment/cash or correct cash with an idempotency key; use refund-task open/list/settle/cancel endpoints for refunds.
3. Load unmatched transactions, candidates, then post allocation. Retry a payment request only via its `/retry` action. Payment webhooks are provider-to-server only.

### Communication (W3, in progress)

1. Manage templates at `/message-templates` (including `/variables` and `/preview`).
2. Create/read/update campaigns at `/campaigns`; preview audience, then `/send`, `/schedule`, or `/cancel` according to current state.
3. Inspect `/deliveries` or `/campaigns/{id}/deliveries`; retry only delivery/campaign retry routes that the server accepts. Suppressions and reminder settings have separate endpoints.

### Phone check-in (W3, in progress)

1. Provision a device token in admin (`/device-tokens`) and configure the phone with `Bearer dev1_…`.
2. `GET /api/check-in/whoami`; then `GET /api/check-in/events/{eventId}/lists` and `/overview` (device path, no org slug).
3. Search `/lists/{listId}/participants` or `POST /lookup`; post scan/check-in using an idempotency key. Use cash check-in/escalation only when exposed/authorized. Team users can use the equivalent `/api/check-in/org/{org}/events/{eventId}/...` paths.

## 8. Client-visible rate limits

| Operation | Limit | Result |
|---|---|---|
| Login | 5 / minute per username+IP, and 50 / hour per IP | 429 |
| Sign-up, invitation preview, invitation accept | shared 20 / hour per IP address | 429 |
| Public order (W3) | 10 / 10 minutes per IP, plus 3 / minute per IP+organizer+event | 429 with `Retry-After` |
| Self-service recovery (W3) | 10 / hour per IP; 3 / hour per organizer+email | still 202, deliberately silent |

Sources: `backend/.env`, `Identity/Infrastructure/Symfony/services.php`, `Registration/Infrastructure/Symfony/services.php`, and `PublicRateLimitListener.php`. Environment can override the Registration defaults; client behaviour must depend on the response, not a local counter.

## 9. Known gaps

- Waitlist UI/action is not an HTTP endpoint yet. `capacity_exhausted` can ask for `join_waitlist`, but no route implements it (P1).
- Attention actions have `kind: command|link`. POST only a returned **command** action to `/attention/{itemId}/actions/{action}`; navigate/render a returned **link** action through the owning module instead. There is no generic resolve/assign command for link actions (`GetAttentionItemController.php`, `ExecuteAttentionActionController.php`).
- There is no general file upload, public news/program read route, or generic account/password-reset endpoint in the current controllers.
- Webhooks are integration endpoints, not frontend features.

## Inconsistencies found

1. `CsrfGuardListener` protects all unsafe `/api/auth` paths, including public sign-up/invitation, while `security.php` describes those endpoints as `PUBLIC_ACCESS`; frontend must still send CSRF (`Shared/UserInterface/Http/CsrfGuardListener.php` vs `config/packages/security.php`).
2. The documented public-order global limiter in `config/packages/framework.php` is 10/10 minutes, while Registration also installs its own 10/10-minutes IP limiter and actually consumes the Registration limiter; the duplicate configuration is not used by `PublicRateLimitListener`.
3. Recovery limiter values are defaults declared by the Registration module (10 IP / 3 email per hour), not present in `backend/.env`; deployment environment can override them.
4. (Checked by the coordinator: not an inconsistency.) `EventController::list()` narrows the admin event list to `AccessPolicy::eventScope()` for event-scoped members (`Catalog/UserInterface/Http/EventController.php:79`); the server, not the client, filters.
