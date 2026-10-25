---
status: accepted
last-verified: 2026-10-24
---

# ADR 008 — Command-oriented HTTP API with thin controllers and value resolvers (no API Platform)

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/*/UserInterface/Http/*Controller.php`, the value resolvers in `backend/src/*/UserInterface/Http/ValueResolver/`, `backend/config/packages/nelmio_api_doc.yaml`, and the generated `frontend/src/api/schema.d.ts`.

## Context

- **One documented API (API-01).** Domain operations go through a documented API, so the web, the check-in client and any future clients share the same rules.
- **Four access zones (API-03, API-04).** Public read and registration without authentication but with rate limiting, self-service with a limited token, check-in, and admin.
- **Behaviour, not CRUD.** Most operations are behaviour: place an order, cancel a participation, transfer it, change its variant, check in, correct a check-in, record cash, allocate a transaction, publish a document version, cancel an event. They are not resource updates.
- **Interview showcase.** The job posting explicitly names value resolvers, compiler passes and service decoration.

## Decision

**Routes.** The API is hand-written and command-oriented, grouped by zone:

| Prefix | Firewall | Example |
|---|---|---|
| `/api/public/…` | none + `RateLimiter` | `GET /api/public/orgs/{orgSlug}/events/{eventSlug}`, `POST …/orders` |
| `/api/self-service/…` | `self_service` | `GET /api/self-service/order`, `POST /api/self-service/participations/{id}/cancel` |
| `/api/check-in/…` | `check_in` | `GET /api/check-in/lists/{listId}/search?q=`, `POST /api/check-in/scan` |
| `/api/admin/org/{orgSlug}/…` | `admin` | `POST /api/admin/org/{orgSlug}/orders/{orderId}/participations/{id}/transfer` |
| `/api/webhooks/pairrot` | `webhook` | inbound only |

- Reads are resources (`GET`).
- Writes are **commands**, named for intent: `POST …/cancel`, `POST …/check-in`, `POST …/cash-payments`, `POST …/document-versions/{id}/publish`.
- Plain resource editing stays in the REST style where it really is editing: `PUT …/events/{id}/details`.

**Controller shape** (one action per invokable controller):

```php
#[Route('/api/admin/org/{orgSlug}/orders/{orderId}/participations/{participationId}/cancel', methods: ['POST'])]
#[IsGranted(Permission::REGISTRATION_CANCEL->value, subject: 'order')]
public function __invoke(
    #[CurrentOrganizer] Organizer $organizer,
    #[MapEntity] Order $order,                       // tenant filter already active
    #[MapRequestPayload] CancelParticipationRequest $body,
    #[IdempotencyKey] ?IdempotencyKey $key,
    #[CurrentActor] Actor $actor,
): JsonResponse {
    $this->commandBus->dispatch(new CancelParticipation($order->id(), $body->participationId, $body->reason, $actor), [$key]);
    return $this->json($this->orderDetail->get($order->id()));   // query service (DBAL → view), no bus
}
```

- Controllers never contain business logic: decode, authorise, dispatch, map the result.
- Request DTOs are `final readonly` classes with Validator attributes. Response DTOs are explicit view models.
- Domain exceptions map to **RFC 9457 problem details** through a `ProblemDetailsExceptionListener` with a per-module mapping of exception class to `{status, type, title}`. Example: `CapacityExhausted` gives `409 /problems/capacity-exhausted` with `waitlistAvailable`.
- Error messages explain the cause and the next step (NFR "Srozumitelnost chyb").

**Custom value resolvers:**
- `#[CurrentOrganizer]`, `#[CurrentMember]`, `#[CurrentActor]`,
- `#[SelfServiceOrder]` (from the participant principal),
- `#[CheckInToken] ParticipationQrToken` (verifies the signature and version),
- `#[IdempotencyKey]` (header `Idempotency-Key` plus a request hash; replays return the stored response, and a different body gives 422).

**Idempotency for unsafe public and check-in commands.** `IdempotencyMiddleware` on `command.bus` stores `(key, request_hash, response)` in `idempotency_record`, scoped by caller.

**Documentation and types.** `nelmio/api-doc-bundle` generates OpenAPI 3.1 from routes, DTOs and attributes. `openapi-typescript` generates `frontend/src/api/schema.d.ts`, and CI fails when the generated file is stale.

**Versioning.** The API is first-party, so it has no version prefix. Breaking changes ship together with the frontend in the monorepo.

## Consequences

**Positive**
- The API reads like the domain.
- Authorisation and decoding happen before the controller.
- Every write passes the same command bus middleware: transaction, tenant, audit and idempotency.
- It showcases value resolvers and `MapRequestPayload`.

**Negative**
- More hand-written code than API Platform: DTOs, views and OpenAPI annotations.
- Collection filtering and pagination for admin tables need a small in-house helper (`PageRequest`, `FilterSet`). That code is shared by Reporting.

## Alternatives considered

- **API Platform with state providers and processors.** Fast for CRUD and gives OpenAPI for free, but commands become custom operations with processors and the domain gets bent to resources. A large part of the DI and decoding would hide behind conventions, so it demonstrates less of the requested Symfony depth. Not ruled out for a future public read API.
- **GraphQL.** Commands still need mutations, and the extra tooling does not fit a first-party client.
- **RPC endpoints without REST reads.** Loses cacheable public `GET`s (PUB, NFR "Výkon").

## Verification

- Functional tests per endpoint for authorisation, validation, problem-details shape and idempotent replay.
- `OpenApiSnapshotTest` diffs the generated document, and the frontend type generation runs in CI.
- An architecture test (PHPStan rule) checks that controllers do not inject repositories or the `EntityManager`; they use only `command.bus` and query services.

## Related

[ADR 005](005-tenant-from-url-doctrine-filter-voters.md), [ADR 006](006-authentication-session-tokens-no-jwt.md), [ADR 010](010-monorepo-nextjs-frontend.md) · [08 Crosscutting concepts](../architecture/08-prurezove-koncepty/README.md), [09 Symfony](../architecture/09-symfony-v-architekture/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

Writes go through the `CommandBus` port and the "every command emits an event" rule is enforced at runtime. Problem details use a list-valued `nextSteps`; CSRF requires a same-origin `Origin` plus any `csrf-token` header value of at least 24 characters. Admin POST routes do not currently require an idempotency key.
