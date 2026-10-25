# Module contracts

> Written by W1 (Contracts). This is the first thing W2 / W3 / W4 agents read: it says what every module offers the others and what it publishes. The source of truth is the code in `backend/src/<Module>/Contract/`; the event catalogue is [`katalog-udalosti.md`](../architecture/06-asynchronni-svet/katalog-udalosti.md). Contracts only: no handler, entity, repository or migration exists yet for anything below.

## Rules that follow from the contracts

1. **Another module is reachable only through its `Contract/`** (facade interfaces, readonly DTOs, integration events, exceptions). `config/module_contracts.php` lists which foreign Contracts each module may use and why; `bin/deptrac-layers` turns it into `deptrac.yaml`. A new edge is a change to that file plus a line in your report. A Contract depends on `Shared` only, so Contracts cannot form cycles.
2. **A module's `Domain` may use its own `Contract`** (to throw its public exceptions and to use its Contract enums) and `Shared`, never a foreign Contract. This relaxes the book's ruleset (`Domain: [Shared]`) by one own-module edge; see "Decisions".
3. **Typed ids that cross a module boundary live in `Shared\Domain\Id`** with a DBAL type in `Shared\Infrastructure\Doctrine` that is registered once by `SharedIdTypes` (no module registers them). Use `#[ORM\Column(type: OrderIdType::NAME)]`. Module-internal ids stay in the module. Enums that several Domains store (`PaymentMethod`, `CapacityScope`, `CapacityUnit`, `FinancialState`) are in `Shared` too; every other enum lives in the Contract of its owner.
4. **Facades are implemented by the owner's Application layer** (`#[AsAlias(XQuery::class)]` on the implementing class) and return DTOs, never entities. Every method takes `OrganizerId` explicitly; an entity of another organizer is "not found". **Writing facades** (`ConfirmationRecorder`, `OrderFinanceInitializer`, `CashDesk`, `CapacityProvisioner`) run in the transaction of the caller, so only a handler already on `command.bus` may call them.
5. **Integration events** are `final readonly`, carry ids, money, enums, flags and times only, and have the same envelope: `organizerId` first (typed `OrganizerId`; `?OrganizerId` for the platform-wide `payments.integration_degraded`) and `occurredAt` last. The `TenantStamp` is still added by `TenantContextMiddleware` when the event is dispatched inside a tenant, and restored in the worker: the subscriber runs with the tenant set. A handler that publishes outside a tenant (webhook processing) enters the tenant first or dispatches with `TenantStamp::of($organizerId)`. Construct events with `ClockInterface::now()`.
6. **Subscribers are scaffolded, not forgotten.** Every event carries `#[PendingSubscribers('payments', 'communication', ...)]` (lower-case module names) with the planned subscribers of the catalogue. `IntegrationEventRoutingPass` accepts an event without a handler only while the attribute lists a module, and **fails the build when a listed module already has a handler**. So the module that writes the handler (`#[AsMessageHandler(fromTransport: '<module>_events')]`, one per event and module) **must remove its name from the attribute in the same change** (and delete the attribute when the list gets empty). `PendingSubscribersTest` prints what is left (`EVENTOR_REPORT_PENDING=1`) and fails while anything is left when `EVENTOR_STRICT_SUBSCRIBERS=1` (W4 flips it). A module that subscribes also declares its transport: `ModuleServices::eventTransport($container, '<module>')`.
7. **Tests for contracts** (`tests/Unit/Shared`): `EventCatalogConsistencyTest` (code vs catalogue table), `ContractEventsShapeTest` (no personal data by shape, allow-listed strings), `ContractEventInvariantsTest`, `ModuleContractMatrixTest` (planned subscribers may import their events), `IdTypesTest`; `tests/Integration/Architecture`: `ContractEventsSerializationTest` (every event round-trips through the real Messenger serializer), `PendingSubscribersTest`. `tests/Support/EventSampler::sample(Event::class, ['field' => ...])` builds a valid event for your own tests.

## Module dependency matrix (`config/module_contracts.php`)

| Module | May use the Contract of | Why |
|---|---|---|
| Identity | Payments | Payments: PairrotOnboarding (the onboarding wizard calls Pairrot through Payments) |
| Catalog | Identity, Registration | Identity: Permission attributes, OrganizerSettings (payment publish rules), identity.pairrot_connected; Registration: CapacityProvisioner / CapacityQuery, registration.capacity_released |
| Legal | Identity, Registration | Identity: Permission attributes, organizer policy; Registration: ParticipationQuery (activity/variant of a participation), registration.participation_transferred |
| Registration | Identity, Catalog, Legal, Payments | Identity: OrganizerLookup (public zone), OrganizerSettings, Permission attributes; Catalog: EventCatalog, FormValidator, FormSchemaProvider, catalog events; Legal: RequirementResolver, ConfirmationRecorder, ConsentQuery, legal events; Payments: OrderFinanceInitializer, FinanceQuery, payments.order_payment_changed / payment_request_issued |
| Payments | Identity, Registration | Identity: OrganizerSettings (Pairrot ids, policy), Permission attributes; Registration: registration.order_placed / order_amount_changed |
| CheckIn | Identity, Catalog, Registration, Payments, Legal | Identity: Permission attributes, OrganizerSettings (cash policy), device authentication; Catalog: EventCatalog (lists), catalog.event_published / event_cancelled / activity_rescheduled; Registration: ParticipationQuery, ParticipationQrTokens, registration events; Payments: CashDesk (RecordCashAndCheckIn), FinanceQuery; Legal: ConfirmationRecorder (confirmation on site, channel staff) |
| Communication | Identity, Catalog, Legal, Registration, Payments, CheckIn | Identity: OrganizerSettings (sender), InvitationQuery, identity.member_invited; Catalog: EventCatalog, ProgramReader, NewsReader, catalog events; Legal: ConsentQuery, legal events; Registration: RecipientQuery, SelfServiceLinks, ParticipationQrTokens, registration events; Payments: FinanceQuery (instructions), payments events; CheckIn: CheckInQuery (segments) |
| Content | Identity, Catalog, Legal | Identity: Permission attributes; Catalog: EventCatalog (collection of an event); Legal: ConsentQuery (likeness consents of a gallery) |
| Privacy | Identity, Catalog, Registration | Identity: Permission attributes; Catalog: catalog.event_completed (retention clock); Registration: PersonDirectory |
| Audit | Identity | Identity: Permission attributes (audit.view.*) |
| Attention | Identity, Registration, Payments, Legal, Communication, CheckIn, Content, Privacy | Identity: Permission attributes; Registration: registration.participation_requires_attention / late_payment_received; Payments: payments events (revoked, failed, overpayment, refund task, unmatched transaction...); Legal: legal.consent_withdrawn; Communication: communication.delivery_failed; CheckIn: checkin.escalation_raised; Content: content.collection_published; Privacy: privacy.data_subject_request_opened |
| Reporting | Identity, Catalog, Registration, Payments, CheckIn, Communication, Privacy | Identity: Permission attributes; Catalog: catalog.event_published; Registration: registration.order_placed / participation_expired; Payments: payments.order_payment_changed / cash_recorded / ledger_entry_corrected; CheckIn: checkin events; Communication: communication.campaign_sent; Privacy: privacy.person_anonymized |

---

## Identity

**Ids in Shared:** OrganizerId (W0), UserAccountId, MembershipId, InvitationId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `OrganizerDirectory` | `findForMember(slug, user)` | W0. Admin zone: the organizer of the URL if the user is a member (404 otherwise) | every admin controller via `#[CurrentOrganizer]` |
| `AccessPolicy` | `isAllowed(permission, organizer, user)`, `roleOf(...)` | W0. Role x permission decisions for voters | voters of all modules |
| `OrganizerLookup` | `findBySlug(slug)`, `get(OrganizerId)` | Organizer without a signed-in member: public zone slug -> organizer, subscribers and jobs | Registration, Catalog, Content (public zone); every subscriber that needs the slug/name |
| `OrganizerSettings` | `paymentSettings`, `policy`, `contact` | Pairrot onboarding state, organizer policy (cash, auto-restore, overpayment, default TTLs), sender data | Catalog (publish rules), Payments, CheckIn, Registration, Communication |
| `InvitationQuery` | `forDelivery(organizer, invitation)` | Invitee e-mail, role, accept URL for the invitation message | Communication |

**Enums:** `OverpaymentHandling`, `Permission`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `MemberInvited` (`identity.member_invited` v1) | `invitationId`: InvitationId, `role`: string | communication |
| `MembershipChanged` (`identity.membership_changed` v1) | `membershipId`: MembershipId, `userAccountId`: UserAccountId, `previousRole`: ?string, `newRole`: ?string, `eventScope`: list<EventId> | identity |
| `PairrotConnected` (`identity.pairrot_connected` v1) | `subtenantId`: string, `bankConnectionId`: string | catalog |

- `MemberInvited`: Catalogue payload lists `organizerId` as a field; it is the envelope field here.
- `MembershipChanged`: `userAccountId` is an addition to the catalogue payload (id only), kept from the W0 event.
- `PairrotConnected`: Catalogue payload lists `organizerId` as a field; it is the envelope field here.

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `InvitationNotFound` | `invitation_not_found` | 404 | The invitation does not exist, expired or belongs to another organizer. |
| `OrganizerNotFound` | `organizer_not_found` | 404 | No organizer with this id (or slug). |


---

## Catalog

**Ids in Shared:** EventId, ActivityId, VariantId, WaveId, AddOnId, FormVersionId, ProgramItemId, NewsPostId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `EventCatalog` | `resolvePublishedSlug(organizer, slug)` | Public URL slug -> published EventId | Registration (`PlaceOrder` controller), Content |
| `EventCatalog` | `registrationSnapshot(organizer, event)` | The published offer: activities, variants, prices, bucket ids, form version, windows, age limits, payment methods, hold TTL, cancellation rules | Registration (`PlaceOrder`, change variant, self-service cancel) |
| `EventCatalog` | `summary`, `summaries` | Names, dates, venue, activities for messages and lists | Communication, CheckIn, Content |
| `FormSchemaProvider` | `currentVersionId(organizer, event)`, `schema(organizer, version)` | Current form version; read model of a version (labels, scope, semantic fields) | Registration (public form endpoint, FRM-11 preview) |
| `FormValidator` | `validate(organizer, version, submission)` | Server-side validation incl. conditions; returns typed answers + semantic fields; throws `FormValidationFailed` | Registration (`PlaceOrder`, corrections) |
| `FormValidator` | `missingRequiredFields(...)` | Required fields without answer for a scope/selection (S17) | Registration (`RequestMissingAnswers`) |
| `ProgramReader` | `forEvent(organizer, event, audience)`, `item(organizer, item)` | Program of an event; one item | Communication (program change campaign); Catalog public page itself |
| `NewsReader` | `post(organizer, post)` | Content of a published news post | Communication (`catalog.news_published` with `broadcast`) |

**Enums:** `AnswerScope`, `ProgramVisibility`, `PublicationState`, `RegistrationState`, `SemanticField`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `EventPublished` (`catalog.event_published` v1) | `eventId`: EventId, `slug`: string, `startsAt`: DateTime, `endsAt`: DateTime, `activityIds`: list<ActivityId> | reporting, catalog, checkin |
| `EventDetailsChanged` (`catalog.event_details_changed` v1) | `eventId`: EventId, `changedFields`: list<string> | catalog |
| `EventCancelled` (`catalog.event_cancelled` v1) | `eventId`: EventId, `reason`: string | registration, communication, checkin |
| `EventCompleted` (`catalog.event_completed` v1) | `eventId`: EventId | communication, privacy |
| `RegistrationStateChanged` (`catalog.registration_state_changed` v1) | `eventId`: EventId, `activityId`: ?ActivityId, `from`: RegistrationState, `to`: RegistrationState | catalog, registration |
| `ActivityRescheduled` (`catalog.activity_rescheduled` v1) | `eventId`: EventId, `activityId`: ActivityId, `previousStartsAt`: DateTime, `newStartsAt`: DateTime, `important`: bool | communication, checkin |
| `CapacityChanged` (`catalog.capacity_changed` v1) | `bucketId`: CapacityBucketId, `scope`: CapacityScope, `previousLimit`: ?int, `newLimit`: ?int, `belowOccupancy`: bool | registration, catalog |
| `FormVersionPublished` (`catalog.form_version_published` v1) | `eventId`: EventId, `formVersionId`: FormVersionId, `previousFormVersionId`: ?FormVersionId, `addedRequiredFieldIds`: list<string> | registration |
| `ProgramItemChanged` (`catalog.program_item_changed` v1) | `eventId`: EventId, `programItemId`: ProgramItemId, `activityId`: ?ActivityId, `important`: bool | communication |
| `NewsPublished` (`catalog.news_published` v1) | `eventId`: EventId, `newsPostId`: NewsPostId, `broadcast`: bool | communication |

- `EventPublished`: `checkin` (default check-in list per activity) is not in the catalogue table; added because nothing else creates the lists.
- `EventCompleted`: `privacy.schedule_retention` is P1.
- `RegistrationStateChanged`: `registration.notify_waitlist_closed` is P1.
- `ProgramItemChanged`: Written as `catalog.program_item_changed` in the catalogue.

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `EventNotFound` | `event_not_found` | 404 | The event does not exist, is not published (public zone) or belongs to another organizer. |
| `FormValidationFailed` | `form_invalid` | 422 | The answers do not satisfy the form (FRM-05, FRM-07). `violations()` lists every offending field so the UI can mark it. |
| `FormVersionNotFound` | `form_version_not_found` | 404 | No such form version. |
| `NewsPostNotFound` | `news_post_not_found` | 404 | No such news post. |
| `ProgramItemNotFound` | `program_item_not_found` | 404 | No such program item. |


---

## Legal

**Ids in Shared:** LegalDocumentId, DocumentVersionId, LegalRequirementId, ConfirmationId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `RequirementResolver` | `forRegistration(organizer, event, slots)` | Effective requirements (organizer -> event -> activity, replacement honoured) for order and per slot | Registration (`PlaceOrder`, public form, FRM-11 preview) |
| `RequirementResolver` | `assertSatisfied(requirements, decisions)` | Pure check that mandatory requirements have a granted decision; throws `RequirementNotSatisfied` | Registration (`PlaceOrder`, before reserving capacity) |
| `ConfirmationRecorder` | `record(organizer, confirmations)` | Appends decisions (granted/declined/withdrawn) to the ledger **in the caller's transaction**; emits `legal.confirmation_recorded` / `consent_withdrawn` | Registration (`PlaceOrder`, self-service, withdraw), CheckIn (confirmation on site, channel `staff`) |
| `ConsentQuery` | `missingFor(organizer, participationIds)` | Requirements a participation still owes (also reconfirmations) | Registration (participation / check-in view) |
| `ConsentQuery` | `grantedAmong(organizer, type, purpose, persons)` | Persons whose latest decision is `granted` (marketing audience) | Communication |
| `ConsentQuery` | `personsWithGrantedConsent(organizer, event, type)` | Persons with a granted consent of a type (likeness check of a gallery) | Content |

**Enums:** `ConfirmationChannel`, `Confirmer`, `ConsentDecision`, `DocumentLevel`, `RequirementType`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `DocumentVersionPublished` (`legal.document_version_published` v1) | `documentId`: LegalDocumentId, `versionId`: DocumentVersionId, `previousVersionId`: ?DocumentVersionId, `level`: DocumentLevel, `scopeId`: Uuid, `requiresReconfirmation`: bool | legal, communication |
| `ReconfirmationRequested` (`legal.reconfirmation_requested` v1) | `requirementId`: LegalRequirementId, `versionId`: DocumentVersionId, `participationIds`: list<ParticipationId>, `orderIds`: list<OrderId> | communication, registration |
| `ConfirmationRecorded` (`legal.confirmation_recorded` v1) | `confirmationId`: ConfirmationId, `requirementId`: LegalRequirementId, `versionId`: DocumentVersionId, `decision`: ConsentDecision, `participationId`: ?ParticipationId, `orderId`: ?OrderId, `channel`: ConfirmationChannel | registration |
| `ConsentWithdrawn` (`legal.consent_withdrawn` v1) | `confirmationId`: ConfirmationId, `requirementId`: LegalRequirementId, `requirementType`: RequirementType, `participationId`: ?ParticipationId, `orderId`: OrderId | attention, communication |

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `ConfirmationNotAllowed` | `confirmation_not_allowed` | 422 | The decision is not allowed for this requirement (declining a mandatory one, withdrawing a non-revocable one). |
| `LegalDocumentNotFound` | `legal_document_not_found` | 404 | No such legal document or version. |
| `RequirementNotSatisfied` | `missing_confirmation` | 422 | A mandatory legal requirement has no granted decision (chapter 04, path 2, step 4). `violations()` names the requirements. |


---

## Registration

**Ids in Shared:** OrderId, ParticipationId, PersonId, CapacityBucketId, WaitlistEntryId, WaitlistOfferId, SelfServiceTokenId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `ParticipationQuery` | `find`, `findMany` | Facts of participations (state, legal/answers state, ids) without personal data | Legal, CheckIn |
| `ParticipationQuery` | `forCheckIn(organizer, id)` | Check-in projection: display name, activity/variant, state, finance, missing confirmations, other participations of the person | CheckIn |
| `ParticipationQuery` | `findByRegistrationCode`, `searchForCheckIn` | Desk lookup by code or name within an activity | CheckIn |
| `OrderQuery` | `get(organizer, order)` | Order with participations, number, current amount due | Legal, CheckIn, Communication |
| `RecipientQuery` | `resolveAudience`, `stillInAudience`, `forOrder` | Campaign audience (one member per orderer and activity), re-check before sending, contact + participants of an order | Communication |
| `AvailabilityReader` | `forEvent(organizer, event)` | Public availability per bucket (cached decorator in production) | Registration public API (frontend composes the page) |
| `CapacityQuery` | `bucket`, `forEvent` | Exact held/confirmed/free counters | Registration admin API (CAP-03) |
| `CapacityProvisioner` | `provision(organizer, event, buckets)`, `changeLimit(...)` | Creates buckets of a publishing event; changes a limit (CAP-06) **in the caller's transaction** | Catalog (`PublishEvent`, `ChangeCapacity`) |
| `SelfServiceLinks` | `urlFor(organizer, order)` | Participant-facing self-service URL (secret derived, never on the bus) | Communication |
| `ParticipationQrTokens` | `issue`, `verify` | Sign / verify `qr1.<payload>.<sig>` incl. `qr_version` | Communication (QR in e-mail), CheckIn (scan) |
| `PersonDirectory` | `search`, `anonymize` | Find and anonymize a person (P1) | Privacy |

**Enums:** `AnswersState`, `AttentionCause`, `CancelledBy`, `CapacityReleaseCause`, `ConfirmationReason`, `LegalState`, `OrderAmountChangeReason`, `ParticipationState`, `RestoredBy`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `OrderPlaced` (`registration.order_placed` v1) | `orderId`: OrderId, `orderNumber`: string, `eventId`: EventId, `paymentMethod`: PaymentMethod, `amountDue`: Money, `participationIds`: list<ParticipationId>, `holdExpiresAt`: ?DateTime | payments, communication, reporting |
| `ParticipationsConfirmed` (`registration.participations_confirmed` v1) | `orderId`: OrderId, `participationIds`: list<ParticipationId>, `reason`: ConfirmationReason | communication, checkin, registration |
| `ParticipationExpired` (`registration.participation_expired` v1) | `orderId`: OrderId, `participationId`: ParticipationId, `bucketIds`: list<CapacityBucketId> | communication, reporting |
| `ParticipationCancelled` (`registration.participation_cancelled` v1) | `orderId`: OrderId, `participationId`: ParticipationId, `by`: CancelledBy, `reason`: string | communication, checkin |
| `ParticipationRequiresAttention` (`registration.participation_requires_attention` v1) | `orderId`: OrderId, `participationId`: ParticipationId, `cause`: AttentionCause | attention |
| `LatePaymentReceived` (`registration.late_payment_received` v1) | `orderId`: OrderId, `participationIds`: list<ParticipationId>, `capacityAvailable`: bool, `autoRestore`: bool | attention |
| `ParticipationRestored` (`registration.participation_restored` v1) | `orderId`: OrderId, `participationId`: ParticipationId, `by`: RestoredBy | communication, attention |
| `ParticipationVariantChanged` (`registration.participation_variant_changed` v1) (P1) | `orderId`: OrderId, `participationId`: ParticipationId, `previousVariantId`: VariantId, `newVariantId`: VariantId, `previousActivityId`: ActivityId, `newActivityId`: ActivityId | communication, checkin |
| `ParticipationTransferred` (`registration.participation_transferred` v1) (P1) | `orderId`: OrderId, `previousParticipationId`: ParticipationId, `newParticipationId`: ParticipationId | communication, legal |
| `OrderAmountChanged` (`registration.order_amount_changed` v1) | `orderId`: OrderId, `previousAmountDue`: Money, `newAmountDue`: Money, `reason`: OrderAmountChangeReason | payments |
| `CapacityReleased` (`registration.capacity_released` v1) | `bucketIds`: list<CapacityBucketId>, `seats`: int, `cause`: CapacityReleaseCause | registration, catalog |
| `AnswersIncomplete` (`registration.answers_incomplete` v1) | `orderId`: OrderId, `participationIds`: list<ParticipationId>, `formVersionId`: FormVersionId | communication |
| `SelfServiceLinkRequested` (`registration.self_service_link_requested` v1) | `orderId`: OrderId, `tokenId`: SelfServiceTokenId | communication |
| `WaitlistOfferMade` (`registration.waitlist_offer_made` v1) (P1) | `offerId`: WaitlistOfferId, `entryId`: WaitlistEntryId, `expiresAt`: DateTime | communication |
| `WaitlistOfferExpired` (`registration.waitlist_offer_expired` v1) (P1) | `offerId`: WaitlistOfferId, `entryId`: WaitlistEntryId | communication |

- `CapacityReleased`: `registration.offer_seat_to_waitlist` is P1.

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `CapacityBucketNotFound` | `capacity_bucket_not_found` | 404 | No such capacity bucket (or the event has none yet). |
| `CapacityExhausted` | `capacity_exhausted` | 409 | A bucket had no room for the order (S01, S02): the whole transaction is rolled back. With `waitlistAvailable` the client offers the waitlist (REG-12, P1). |
| `InvalidQrToken` | `qr_token_invalid` | 422 | The QR token is malformed, has a bad signature or an outdated version (CHK-03). |
| `NotEligible` | `not_eligible` | 422 | The participant does not meet the conditions of the variant: age at the reference date, category, team size (chapter 04, path 2, step 2). |
| `OrderNotFound` | `order_not_found` | 404 | No such order for this organizer. |
| `ParticipationNotFound` | `participation_not_found` | 404 | No such participation for this organizer. |
| `PersonNotFound` | `person_not_found` | 404 | No such person for this organizer. |
| `RegistrationNotOpen` | `registration_closed` | 409 | Registration of the event or of a selected activity is not open (not yet, paused or closed). |


---

## Payments

**Ids in Shared:** PaymentRequestId, LedgerEntryId, RefundTaskId, BankTransactionId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `FinanceQuery` | `forOrder`, `summaries` | Folded financial state (+ split transfer/cash/refunded) of one / many orders | Registration, CheckIn, Communication |
| `FinanceQuery` | `paymentInstructions(organizer, order)` | Active payment instructions (VS, IBAN, SPAYD, due); empty while the request is pending | Registration (self-service), Communication (e-mail with QR) |
| `OrderFinanceInitializer` | `initialize(organizer, order, method, amountDue)` | Creates `order_finance` (unpaid / not_required) **in the `PlaceOrder` transaction** | Registration |
| `CashDesk` | `recordCash(organizer, order, amount, station, key, note)` | Cash ledger entry + financial state **in the caller's transaction**; idempotent per key; throws `CashNotAllowed` | CheckIn (`RecordCashAndCheckIn`) |
| `PairrotOnboarding` | `activateSubtenant`, `connectBankAccount`, `receptionState` | Pairrot calls of the ORG-02 wizard (idempotent) | Identity (onboarding wizard) |

**Enums:** `MatchingResult`, `PaymentRequestPurpose`, `PaymentRequestStatus`, `ReceptionState`, `RefundReason`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `PaymentRequestIssued` (`payments.payment_request_issued` v1) | `orderId`: OrderId, `paymentRequestId`: PaymentRequestId, `variableSymbol`: string, `amount`: Money, `dueAt`: DateTime, `purpose`: PaymentRequestPurpose | communication, registration |
| `PaymentRequestFailed` (`payments.payment_request_failed` v1) | `orderId`: OrderId, `paymentRequestId`: PaymentRequestId, `attempts`: int, `lastError`: string | attention |
| `PaymentRevoked` (`payments.payment_revoked` v1) | `orderId`: OrderId, `paymentRequestId`: PaymentRequestId, `amount`: Money | attention |
| `IntegrationDegraded` (`payments.integration_degraded` v1) | `integration`: string, `reason`: string, `since`: DateTime | attention |
| `OrderPaymentChanged` (`payments.order_payment_changed` v1) | `orderId`: OrderId, `previousState`: ?FinancialState, `newState`: FinancialState, `delta`: Money, `receivedTotal`: Money, `amountDue`: Money, `ledgerVersion`: int | registration, communication, reporting |
| `OverpaymentDetected` (`payments.overpayment_detected` v1) | `orderId`: OrderId, `overpaidAmount`: Money | attention, payments |
| `RefundTaskOpened` (`payments.refund_task_opened` v1) | `refundTaskId`: RefundTaskId, `orderId`: OrderId, `amount`: Money, `reason`: RefundReason | attention |
| `RefundTaskSettled` (`payments.refund_task_settled` v1) | `refundTaskId`: RefundTaskId, `orderId`: OrderId, `amount`: Money | attention, communication |
| `CashRecorded` (`payments.cash_recorded` v1) | `orderId`: OrderId, `ledgerEntryId`: LedgerEntryId, `amount`: Money, `stationId`: ?CheckInStationId | reporting |
| `LedgerEntryCorrected` (`payments.ledger_entry_corrected` v1) | `orderId`: OrderId, `ledgerEntryId`: LedgerEntryId, `compensatesId`: LedgerEntryId, `amount`: Money | reporting |
| `UnmatchedTransactionReceived` (`payments.unmatched_transaction_received` v1) | `transactionId`: BankTransactionId, `amount`: Money, `variableSymbol`: ?string, `matchingResult`: MatchingResult, `bookedOn`: DateTime | attention |
| `TransactionAllocated` (`payments.transaction_allocated` v1) | `transactionId`: BankTransactionId, `paymentRequestId`: PaymentRequestId, `orderId`: OrderId | attention |
| `PaymentDisputeOpened` (`payments.payment_dispute_opened` v1) | `orderId`: OrderId, `paymentRequestId`: PaymentRequestId, `reviewCaseId`: string | attention |

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `BankAccountUnavailable` | `bank_account_unavailable` | 409 | The bank account is already connected to another subtenant (Pairrot `409 account_unavailable`). |
| `InvalidBankAccount` | `invalid_bank_account` | 422 | Pairrot refused the account number or IBAN (format, check digits or unsupported bank); the onboarding wizard shows it at the input. |
| `CashNotAllowed` | `cash_not_allowed` | 403 | The organizer policy does not let this actor record cash (`check_in_may_record_cash`, role). |
| `OrderFinanceNotFound` | `order_finance_not_found` | 404 | The order has no finance row (unknown order, or `OrderFinanceInitializer` was not called). |
| `PairrotUnavailable` | `pairrot_unavailable` | 503 | Pairrot did not answer (network, 5xx, 429, open circuit breaker). Retryable; crosses the boundary for the onboarding wizard. |


---

## CheckIn

**Ids in Shared:** CheckInId, CheckInListId, CheckInStationId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `CheckInQuery` | `lists`, `recordFor`, `checkedIn`, `counts` | Check-in lists of an event, valid check-in of a participation, "arrived" narrowing, live counters | Communication (segments); CheckIn/Reporting dashboards |

**Enums:** `CheckInSituation`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `ParticipationCheckedIn` (`checkin.participation_checked_in` v1) | `checkInId`: CheckInId, `participationId`: ParticipationId, `listId`: CheckInListId, `stationId`: ?CheckInStationId, `checkedInAt`: DateTime | reporting |
| `CheckInVoided` (`checkin.check_in_voided` v1) | `checkInId`: CheckInId, `participationId`: ParticipationId, `replacedBy`: ?CheckInId | reporting |
| `EscalationRaised` (`checkin.escalation_raised` v1) | `participationId`: ParticipationId, `listId`: CheckInListId, `situation`: CheckInSituation | attention |

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `AlreadyCheckedIn` | `already_checked_in` | 409 | The participation already has a valid check-in (S10, CHK-06): the desk shows when, by whom and where. Only a manager can correct it (CHK-07). |
| `CheckInListNotFound` | `check_in_list_not_found` | 404 | No such check-in list. |


---

## Communication

**Ids in Shared:** CampaignId, DeliveryId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `CampaignComposer` | `preview`, `propose` | How many deliveries an audience gives; draft of a campaign | Communication admin API (frontend composes the reschedule dialog) |

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `CampaignSent` (`communication.campaign_sent` v1) | `campaignId`: CampaignId, `deliveryCount`: int, `skippedCount`: int | reporting |
| `DeliveryFailed` (`communication.delivery_failed` v1) | `deliveryId`: DeliveryId, `campaignId`: ?CampaignId, `trigger`: string, `error`: string | attention |

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `CampaignNotFound` | `campaign_not_found` | 404 | No such campaign. |


---

## Content

**Ids in Shared:** PhotoId, CollectionId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `PublicContentReader` | `collectionsFor`, `photos`, `materials` | Galleries, external collections, materials with signed URLs | Content public / self-service API |

**Enums:** `CollectionVisibility`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `PhotoUploaded` (`content.photo_uploaded` v1) | `photoId`: PhotoId, `collectionId`: CollectionId, `storageKey`: string | content |
| `CollectionPublished` (`content.collection_published` v1) (P1) | `collectionId`: CollectionId, `eventId`: EventId, `visibility`: CollectionVisibility | attention |

- `CollectionPublished`: `attention.warn_missing_likeness_consents` (POST-06).

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `CollectionNotFound` | `collection_not_found` | 404 | No such collection, or it is not visible to this audience. |


---

## Privacy

**Ids in Shared:** DataSubjectRequestId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `PersonDataLocator` | `find(organizer, search)` | Person across modules (P1) | Privacy admin API |

**Enums:** `DataSubjectRequestType`, `RetentionClass`

### Events

Envelope of every event: `organizerId` first, `occurredAt` last (omitted below).

| Event (`name` v) | Payload | Planned subscribers |
|---|---|---|
| `DataSubjectRequestOpened` (`privacy.data_subject_request_opened` v1) (P1) | `requestId`: DataSubjectRequestId, `type`: DataSubjectRequestType, `dueAt`: DateTime | attention |
| `PersonAnonymized` (`privacy.person_anonymized` v1) (P1) | `personRef`: PersonId, `retentionClass`: RetentionClass, `orderIds`: list<OrderId> | reporting |

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `DataSubjectRequestNotFound` | `data_subject_request_not_found` | 404 | No such data subject request. |


---

## Audit

**Ids in Shared:** -

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `AuditTrail` | `forEntity(organizer, type, id)` | W0. History of one entity | Audit admin API (AUD-04) |

### Events

None (see "Decisions").


---

## Attention

**Ids in Shared:** AttentionItemId

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `AttentionQueue` | `summary`, `forSubject` | Open items by type; items about a subject | Attention admin API, dashboards |

**Enums:** `AttentionState`

### Events

None (see "Decisions").

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `AttentionItemNotFound` | `attention_item_not_found` | 404 | No such attention item. |


---

## Reporting

**Ids in Shared:** -

### Facades

| Facade | Method | Purpose | Consumers |
|---|---|---|---|
| `ReportQuery` | `eventSummary(organizer, event)` | Participation / finance / check-in / messages counts | Reporting admin API |
| `CsvExporter` | `export(organizer, type, event)` | CSV export to the exports storage with a 24 h URL | Reporting admin API |

**Enums:** `ExportType`

### Events

None (see "Decisions").

### Exceptions (`Contract/Exception`, `#[AsProblem]`)

| Class | Problem code | HTTP | Meaning |
|---|---|---|---|
| `ExportNotPermitted` | `export_not_permitted` | 403 | The actor may not export this kind of data (for example a check-in operator asking for finance). |


---

## Decisions where the book is ambiguous or contradicts itself

| # | Topic | Chosen | Why |
|---|---|---|---|
| 1 | Envelope | `organizerId` (typed) first and `occurredAt` last in every event. The catalogue sample's `eventId` (message id) is dropped. | It collides with the Catalog `eventId` of the payload; Messenger already carries a transport message id, and subscribers must be idempotent on natural keys anyway. `organizerId` makes handlers independent of the stamp. |
| 2 | `Domain` -> own `Contract` | allowed in deptrac | A Domain throws the public exceptions of its module (`CapacityExhausted`) and uses its Contract enums. Chapter 02 puts `CapacityExhausted` in `Domain` while Contract exceptions are the only ones other modules may catch; this reconciles both without duplicating classes. Foreign Contracts remain forbidden for `Domain`. |
| 3 | Foreign Contracts per module | explicit matrix instead of "any Contract" | Chapter 02 lists specific edges; the matrix is derived from facades used + events subscribed. |
| 4 | Who owns capacity | `capacity_bucket` stays Registration's table (ch. 02/03); Catalog assigns bucket ids, calls `CapacityProvisioner` in its transaction (ch. 04 path 1) and publishes `catalog.capacity_changed` (catalogue). | The book has Catalog creating buckets (ch. 04), Registration owning the table (ch. 02/03) and `catalog.capacity_changed` carrying a `bucketId`. Registration's subscriber answers a raised limit with `registration.capacity_released(limit_raised)`. |
| 5 | `OrderFinanceInitializer` | included (sync, in `PlaceOrder`) | Chapter 04 path 2 step 8 has it; chapter 02 lists no such shared transaction. The flow wins: self-service can show the financial state at once. Payments reads everything else from `registration.order_placed` (incl. `orderNumber`), so it needs no Registration facade. |
| 6 | `identity.membership_changed` | W0's `role` + `change` replaced by the catalogue's `previousRole` / `newRole` / `eventScope`; `userAccountId` kept | Granting has `previousRole = null`, revoking `newRole = null`. `MembershipId` and `UserAccountId` moved to Shared to be typed in the event. |
| 7 | `identity.member_invited`, `identity.pairrot_connected` | `organizerId` only in the envelope field | The catalogue lists it as a payload field; same value. |
| 8 | Planned subscribers | `checkin` added to `catalog.event_published` (default check-in list per activity) | Nothing else creates the lists. |
| 9 | `catalog.registration_state_changed.from/to`, `registration.order_amount_changed.reason`, `payments.integration_degraded.integration` | enums for the first two, plain string for the integration name | Closed sets are enums; the integration name is open (`pairrot`). |
| 10 | Self-service secret | `SelfServiceLinks::urlFor()`; `registration.self_service_link_requested` carries only `tokenId` | The DB stores only a hash (ch. 04), so the URL must be derivable (e.g. HMAC of the token id) for every message that links to self-service. W3 Registration decides the derivation. |
| 11 | Facade names | ch. 04 `ApplicableRequirementsResolver` = `RequirementResolver::forRegistration`; `AudienceQuery` = `RecipientQuery`; `ConfirmationSetValidator` = `RequirementResolver::assertSatisfied`; `FormValidator` and `FormSchemaProvider` both exist | Chapter 02 names win; the ch. 04 behaviours are methods of them. |
| 12 | Onboarding | `Payments\Contract\PairrotOnboarding` (not in the ch. 02 table) | Identity owns the wizard and the settings (ch. 03), Payments owns the Pairrot port (ch. 07); the wizard needs a seam. |
| 13 | `attention` events | none emitted (`AttentionItemResolved` of ch. 02 is not in the catalogue) | Items are closed by reacting to the resulting domain events. Audit and Reporting emit nothing by design. |
| 14 | Form preview (FRM-11) | belongs to Registration (it already uses Catalog and Legal) | Catalog has no edge to Legal. |
| 15 | `FormValidationFailed` / `RequirementNotSatisfied` | expose `violations()`; `ProblemFactory` does not read it yet | Request to the coordinator (see the W1 report): until then the problem carries a count only. |
| 16 | Problem titles | added to `translations/problems.{cs,en}.php` | Titles of the 29 new problem codes. |

## Notes for implementers

- **PlaceOrder order of work** (ch. 04): `EventCatalog::registrationSnapshot` -> `FormValidator::validate` -> eligibility -> price -> `RequirementResolver::forRegistration` + `assertSatisfied` -> capacity (throws `CapacityExhausted` for the first full bucket, sorted by id) -> aggregate -> `ConfirmationRecorder::record` -> `OrderFinanceInitializer::initialize` -> token -> `registration.order_placed` (via the domain event + publisher).
- **Cash at check-in:** `CashDesk::recordCash` and the check-in insert share one transaction; the participations become `confirmed` a moment later, when Registration handles `payments.order_payment_changed`. CheckIn must not wait for that.
- **Personal data:** DTOs of facades may carry it (`ParticipationCheckInView::displayName`, `RecipientContact`, `InvitationDeliveryView`) for the one caller that must show it; events, logs and audit never do.
- **Ids are strings on the wire and typed in code:** the serializer (`IdNormalizer`, `MoneyNormalizer`) handles them; lists of ids need a `@param list<XId>` docblock on the event constructor (the shape test enforces it).
