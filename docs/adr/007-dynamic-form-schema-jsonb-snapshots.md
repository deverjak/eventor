---
status: accepted
last-verified: 2026-10-24
---

# ADR 007 — Dynamic registration form: immutable JSONB schema snapshots, typed answers, server-side validation from one source

**Status:** accepted 2026-10-24 · design only, no code yet. Proof will be `backend/src/Catalog/Domain/Form/FormSchema.php`, `FieldTypeRegistryPass`, `backend/src/Registration/Application/Form/AnswerValidator.php`, and the scenario test `S17_RequiredFieldAddedAfterRegistrationsTest`.

## Context

The form editor (FRM) needs:

- field types (FRM-01) and their constraints (FRM-02),
- **scopes** per order, person, participation or group (FRM-03),
- semantic predefined fields such as first name, last name, birth date, email, phone and legal guardian, which the system understands for age, categories and notifications (FRM-04),
- simple conditions on activity, variant or another answer (FRM-05, FRM-06),
- **server-side validation from the schema, not from visibility** (FRM-07),
- **versioning:** every change after publication creates a new version, and answers carry the version they were given in (FRM-08),
- consistency after changes: a new required field triggers a request to fill it in, and never a seemingly filled answer (FRM-09, S17),
- filtering and dynamic columns by custom fields (ADM-04, ADM-05).

The Next.js frontend must render the same form from the same definition.

## Decision

**The schema is an immutable snapshot.**
- `form_version(id, organizer_id, event_id, number, schema jsonb, published_at, created_by)` holds a serialised `FormSchema`: a list of `FieldDefinition {key, type, label, help, required, scope, constraints, semantic?, condition?, appliesTo{activityIds?, variantIds?}}`.
- A draft is mutable. **Publishing freezes it.** Editing a published form creates a draft for version `number + 1`.
- Every field `key` is stable across versions; renaming the label keeps the key.

**Field types are tagged services.**
- Each type (`ShortText`, `LongText`, `Number`, `Date`, `Email`, `Phone`, `SingleChoice`, `MultiChoice`, `YesNo`, `InfoText`, `LegalRequirementField`) is a service implementing `FieldType` and tagged through `#[AsFieldType('short_text')]`. Each provides:
  - `constraints(FieldDefinition): Constraint[]` (Symfony Validator),
  - `normalize(mixed): mixed` (canonical stored value),
  - `jsonSchema(FieldDefinition): array` (the fragment sent to the frontend).
- `FieldTypeRegistryPass` builds a service locator keyed by type name. **At container compile time** it fails if a type lacks any of the three capabilities or if two types share a name.

**Answers are typed JSONB tied to a version.**
- `answer_set(id, organizer_id, order_id, scope, subject_id /* person|participation|group|order */, form_version_id, values jsonb, updated_at)`.
- Values are stored normalised (ISO dates, numbers as numbers, choice keys and not labels).
- A GIN index on `values` (`jsonb_path_ops`) supports filtering by custom fields.

**Semantic fields are projected into typed columns.** First name, last name, birth date, email, phone and guardian reference are written to `person` columns in the same transaction. Age, categories (EVT-08), communication and check-in search therefore never parse JSONB, and GDPR anonymisation (PRV-04) knows exactly which columns to clear.

**Validation comes from one source (FRM-07).**
- `AnswerValidator` takes the `FormVersion`, the selected activities and variants, and the submitted values.
- It evaluates conditions server-side, building the set of applicable fields.
- It builds a Symfony `Collection` constraint from the `FieldType::constraints()` of every applicable field and validates.
- Hidden fields are rejected if present, and required applicable fields must be present.
- The frontend receives `GET /api/public/orgs/{orgSlug}/events/{eventSlug}/form` (the JSON Schema fragments plus conditions) and only mirrors the validation for UX.

**Changes after registrations (FRM-09, S17).**
- Existing `answer_set` rows keep their old `form_version_id`. They are interpreted against that version and never silently migrated.
- When a new version adds a required field, the subscriber `RequestMissingAnswersOnFormVersionPublished` computes the affected subjects and sends a "please complete" message through Communication. It also marks them in the admin view as incomplete for that version.
- Completing the field writes the answer with the new version.
- No default value is ever written on the participant's behalf.

**Sensitive fields (FRM-10, P1).** Field definitions can carry `sensitivity: SPECIAL_CATEGORY` and `retentionClass`. The editor requires both for health-like fields, and Privacy uses them to decide anonymisation.

## Consequences

**Positive**
- History is interpretable forever.
- Validation and UI come from one definition.
- New field types are added without touching the validator.
- Semantic data stays queryable and erasable.

**Negative**
- Reporting across versions must merge field keys. Stable keys make that a union.
- JSONB values are not strongly typed at the DB level. Normalisation is enforced in PHP, and filters cast through typed expressions.

## Alternatives considered

- **EAV tables (`answer(field_id, value_text, value_number, …)`).** Heavy joins for every admin table and awkward versioning.
- **Symfony Form component as the schema.** It renders server-side HTML and does not serve a Next.js frontend or a stored versioned definition.
- **Editing a single mutable schema with soft-deleted fields.** It breaks FRM-08 and S17.
- **A generic rule engine for conditions.** Explicitly out of scope (chapter 10), so conditions stay a small closed set of operators.

## Verification

- `FieldTypeRegistryPassTest`: a misconfigured type fails `cache:warmup`.
- `AnswerValidatorTest`: one test per type, plus conditions, scope applicability, and hidden or extra fields rejected.
- `S17_RequiredFieldAddedAfterRegistrationsTest`: 50 orders on v1, publish v2 with a new required field; old answers are unchanged, 50 requests are queued, and no answer exists for the new key.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 009](009-postgresql.md), [ADR 012](012-legal-documents-content-addressed-versions.md) · [03 Data](../architecture/03-data/README.md), [09 Symfony](../architecture/09-symfony-v-architekture/README.md) · Supersedes: None.

## Implementation note (2026-10-25)

A form draft is a `form_version` row with `published_at IS NULL`. The FRM-11 preview belongs to Registration, and `maxLength = 0` / `maxChoices = 0` are currently validated as 1.
