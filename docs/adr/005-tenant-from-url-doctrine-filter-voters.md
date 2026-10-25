---
status: accepted
last-verified: 2026-10-24
---

# ADR 005 — Tenant from the URL, Doctrine SQL filter, authorization by voters

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/Identity/UserInterface/Http/CurrentOrganizerValueResolver.php`, `backend/src/Shared/Infrastructure/Doctrine/OrganizerFilter.php`, the voters in `backend/src/*/UserInterface/Security/`, and `tests/Integration/Security/TenantIsolationTest.php`.

## Context

- **Strict tenant isolation (NFR "Multitenance").** Organizers' data is strictly separated, and every query and operation is tenant-scoped.
- **Several organizers per account (ORG-04, P1).** One account can be a member of several organizers and switch between them.
- **Permissions (chapter 3).** Authorization is a matrix of **action × role**. The roles `OWNER`, `EVENT_MANAGER`, `FINANCE` and `CHECK_IN` can optionally be restricted to specific events.
- **Reduced view for check-in.** The check-in role sees a reduced projection of personal data.

main-panel learned two lessons the hard way:

- **Ambient tenant state** (a cookie fallback) caused wrong-tenant writes, and ADR 005 there removed it.
- **RLS as the only guard** is brittle when the application connects as one role.

## Decision

**The tenant always comes from the URL.**
- Every admin route lives under `/api/admin/org/{orgSlug}/…`. There is no session-stored "current organizer".
- `CurrentOrganizerValueResolver` (for arguments typed `CurrentOrganizer` or marked `#[CurrentOrganizer]`) resolves the slug and loads the `Membership` of the authenticated account. It then:
  - returns 404 when the organizer does not exist, **or** when the account is not a member, so the existence of foreign tenants is not revealed,
  - stores the organizer in `TenantContext`, a request-scoped service that also implements `ResetInterface` for FrankenPHP worker mode,
  - enables the Doctrine SQL filter `organizer` with the parameter `organizer_id`.
- The guard order is 401 (no session) → 404 (no organizer or no membership) → 403 (the voter denies).

**Data scoping.**
- Every tenant-owned table has `organizer_id uuid not null`, indexed, and part of the unique constraints (for example `(organizer_id, slug)`).
- Entities implement `TenantOwned`.
- `OrganizerFilter::addFilterConstraint()` appends `organizer_id = :organizer_id` for every `TenantOwned` entity.
- DBAL queries in repositories must take a `TenantId` argument. A PHPStan rule flags raw `Connection::executeQuery` calls in `*/Infrastructure/*Repository*` that lack it.

**Non-admin channels.** Public, self-service, check-in and webhook requests do not use the URL slug.
- Self-service and check-in derive the tenant from the token's order or device and set `TenantContext` in their authenticators.
- Public endpoints resolve the event by `(orgSlug, eventSlug)` and read only published data.
- Workers and subscribers set `TenantContext` from the message `TenantStamp`. A `TenantContextMiddleware` on the buses wraps every handler in it and resets it afterwards.

**Authorization.**
- A `Permission` enum lists the actions from the matrix in chapter 3 (`EVENT_EDIT`, `PARTICIPANT_VIEW_FULL`, `PARTICIPANT_VIEW_CHECKIN`, `CASH_RECORD`, `CASH_CORRECT`, `REFUND_CLOSE`, `CHECKIN_CORRECT`, …).
- `RolePermissionMatrix` maps roles to permissions. It is a single PHP class, and the matrix table in the book is generated from it.
- Voters (`EventVoter`, `OrderVoter`, `PaymentVoter`, `CheckInVoter`, `LegalVoter`) take `(Permission, subject)` and check:
  - the membership role,
  - the event restriction (`Membership.eventIds`),
  - organizer configuration such as "event manager may close payment exceptions" and "check-in may record cash".
- Controllers use `#[IsGranted(Permission::X->value, subject: 'event')]`. Command handlers re-check through `AuthorizationCheckerInterface` when they are invoked from non-HTTP paths.

**Personal-data projections.** Read DTOs come in a full and a reduced shape (`ParticipantFullView`, `ParticipantCheckInView`). The query handler picks the shape from the voter decision, not from the client, so the check-in role never receives full data (NFR "Autorizace").

**Postgres RLS is deferred to P1 hardening** as a second line of defence. Each transaction runs `SET LOCAL app.organizer_id`, with policies on tenant tables. It is not part of P0, because the filter, voters and isolation tests already cover the requirement.

## Consequences

**Positive**
- One explicit tenant source, and URLs that can be shared within a team.
- An isolation leak requires bypassing both the resolver and the filter.
- Voters keep the permission matrix testable in one place.

**Negative**
- Doctrine filters do not apply to DBAL queries, hence the PHPStan rule and the isolation tests.
- Every admin URL carries the slug.

**Operational**
- Exports and reporting queries run through query handlers that take `TenantId` explicitly.

## Alternatives considered

- **Session-stored current organizer.** Ambient state causes wrong-tenant actions across tabs, as main-panel experienced.
- **Subdomain per tenant.** Adds DNS and cookie complexity for no gain in a demo.
- **Schema per tenant / database per tenant.** Migrations and reporting multiply, and cross-organizer accounts get harder.
- **RLS only.** Brittle with a single application role and invisible in PHP tests. Kept as a second line for P1.

## Verification

- `TenantIsolationTest` creates two organizers and, for every admin route in the OpenAPI document, asserts that a member of A gets 404 on B's resources.
- `RolePermissionMatrixTest` asserts every cell of the chapter 3 matrix.
- Check-in role API tests assert that the response contains no fields outside `ParticipantCheckInView`.

## Related

[ADR 006](006-authentication-session-tokens-no-jwt.md), [ADR 008](008-command-oriented-http-api.md) · [08 Crosscutting concepts](../architecture/08-prurezove-koncepty/README.md), [09 Symfony](../architecture/09-symfony-v-architekture/README.md) · Supersedes: None.
