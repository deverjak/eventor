---
status: accepted
last-verified: 2026-10-24
---

# ADR 012 — Legal documents as immutable, content-addressed versions; confirmations bound to a version

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/Legal/Domain/DocumentVersion.php`, `backend/src/Legal/Infrastructure/Storage/ContentAddressedStore.php`, the `confirmation` table with its grants, and the scenario tests `S13`–`S16`.

## Context

- **Three content kinds (LEG-01).** A document is inline text, an uploaded PDF, or an **external link, for which an immutable copy of the content must be stored at the moment the version is published**.
- **Scope (LEG-02).** A document has a level: organizer, event or activity. A lower level can add to or replace a document from a higher level, and the UI shows what applies to a given registration.
- **Immutable versions (LEG-03).** Every published version is immutable and traceable, and a registration references a concrete version.
- **Requirement types (LEG-04, LEG-05).** Five types must never be merged into one checkbox. Each requirement says who confirms it: the orderer, each participant, or the legal guardian.
- **Confirmation records (LEG-07).** A record holds the requirement, version, person, participation or order, time, decision and channel.
- **Reconfirmation (LEG-08, S15).** A new version flagged "requires reconfirmation" moves the affected participations to `requires_attention` and sends a request; silence is not consent. Without the flag (S14), old registrations stay bound to the old version.
- **Withdrawal (LEG-09, S16).** Withdrawing consent does not cancel the registration. For likeness permission, it creates an operational task.
- **Minors (LEG-11).** The guardian confirms per child.
- **Transfer (LEG-12, P1).** A transferred participation needs new confirmations from the new person.

## Decision

**Model (module `Legal`).**
- `LegalDocument(id, organizer_id, level, scope_id, kind, title)` owns `DocumentVersion(id, document_id, number, content_sha256, storage_key, mime, source_url?, fetched_at?, published_at, published_by, requires_reconfirmation bool)`. A published version is immutable: no setters, and the app role lacks `UPDATE` on published rows, enforced by a trigger that rejects updates when `published_at IS NOT NULL`.
- `LegalRequirement(id, organizer_id, document_id, type, purpose, scope_text, validity, revocable, confirmer /* ORDERER|EACH_PARTICIPANT|GUARDIAN */, applies_to {activityIds?, variantIds?}, mandatory)`.
  - It is bound to the *document*. The version applied is the current published one at registration time.
  - A `RequirementPolicy` rejects invalid combinations at save time: a mandatory `CONSENT` (LEG-06) or a pre-ticked voluntary consent.

**Content-addressed storage.**
- Publishing a version computes the SHA-256 of the canonical bytes:
  - text: UTF-8 normalised to NFC and rendered to Markdown,
  - PDF: the raw bytes,
  - external link: the fetched response body (HTML or PDF), fetched by `ExternalDocumentFetcher` with a size limit and timeout and stored with `source_url` and `fetched_at`.
- The bytes are written to Flysystem storage (MinIO locally) at `legal/<sha256[0:2]>/<sha256>.<ext>`. Identical content is stored once.
- The version row stores the hash, so tampering is detectable by re-hashing.

**Which documents apply.** `ApplicableRequirementsResolver` returns, for a set of selected activities and variants, the effective requirements and versions. It merges organizer → event → activity, honouring lower-level replacement. The same resolver feeds:

- the public registration form,
- the server-side registration check inside `PlaceOrderHandler` (shared transaction with Registration),
- the admin preview (FRM-11, LEG-02).

**Confirmations are append-only.**
- `confirmation(id, organizer_id, requirement_id, document_version_id, person_id, participation_id?, order_id?, decision /* GRANTED|DECLINED|WITHDRAWN */, channel /* WEB|STAFF */, note?, actor, decided_at)`.
- A later decision is a new row and never an update. The current state is the latest row per `(requirement, person, participation|order)`.
- For minors, `person_id` is the child and `actor` is the guardian person (LEG-11, S13).

**Reconfirmation flow (S15).**
- `DocumentVersionPublished{requiresReconfirmation: true}` is handled by `RequestReconfirmationOnVersionPublished`. It finds the latest confirmations on older versions for active participations, emits `ReconfirmationRequested`, and moves those participations to `requires_attention` through the participation workflow, which is audited. Communication then sends the request.
- A new `GRANTED` row on the new version resolves the attention.
- Without the flag (S14), nothing changes for existing registrations, and new registrations use the new version.

**Withdrawal (S16).** Self-service `POST /api/self-service/confirmations/{id}/withdraw` checks `revocable` and appends `WITHDRAWN`. It emits `ConsentWithdrawn`, which notifies the organizer and, for `LIKENESS`, creates an `AttentionItem` "review published photos". The registration is untouched.

## Consequences

**Positive**
- Every confirmation is provably tied to the exact bytes the person saw.
- History is never overwritten.
- One resolver means the UI and the server cannot disagree about what applies.

**Negative**
- The external-link snapshot may differ from what the participant sees live. The snapshot is authoritative and shown as "version of <date>".
- Storage grows with versions, mitigated by deduplication through hashing.

**GDPR**
- Confirmations are personal data with their own retention class `CONFIRMATION` (PRV-05). On erasure (PRV-04), `person_id` stays linked to the anonymised person. The record of the decision is retained as long as it is legally required.

## Alternatives considered

- **Storing the version text inline in the DB.** Fine for text, poor for PDFs and external snapshots, and it gives no integrity check.
- **Linking to the external URL only.** Explicitly rejected by LEG-01.
- **A single `consents` JSON on the participation.** It loses versions, history and the five distinct types (LEG-04).

## Verification

- `S13_ParentWithTwoChildrenTest`: guardian confirmations exist only for the children, and one payment request.
- `S14_NewVersionWithoutReconfirmationTest` and `S15_NewVersionWithReconfirmationTest`.
- `S16_LikenessWithdrawalTest`: the registration stays active, an attention item exists, and the organizer is notified.
- `ContentAddressedStoreTest`: the same content gives the same key, and re-hashing detects tampering.
- `RequirementPolicyTest`: a mandatory marketing consent is rejected.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 007](007-dynamic-form-schema-jsonb-snapshots.md), [ADR 011](011-workflow-component-state-machines.md) · [03 Data](../architecture/03-data/README.md), [08 Crosscutting concepts](../architecture/08-prurezove-koncepty/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

Versions begin as drafts and receive their hash and storage key when the draft is created. Legal uses the media bucket under `legal/`. The voluntary-consent rule covers `consent` and `likeness`; `preChecked` exists only for rejection. S14 leaves existing registrations unchanged, and minor status is derived from guardian rows through `RegistrationSlot.minor`.
