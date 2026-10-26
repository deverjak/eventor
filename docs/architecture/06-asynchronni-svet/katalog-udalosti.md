[Architektura](../README.md) · [06 Asynchronní svět](README.md) · **Katalog událostí**

# Katalog integračních událostí

Toto je stav kontraktů W0–W3 a modulu Content (W4), ověřený podle `backend/src/<Modul>/Contract/Event/*.php` dne 26. 10. 2026; sloupec „Čeká na odběr“ vypisuje moduly z `#[PendingSubscribers]`, tedy odběratele, kteří ještě nejsou napsaní. Každá zpráva je `final readonly`, nese `organizerId` jako první pole a `occurredAt` jako poslední pole (u `payments.integration_degraded` je `organizerId` volitelný). Pole v tabulkách jsou přesně parametry konstruktoru mezi obálkami; u uvedené výjimky je volitelný `organizerId` vypsán pro úplnost. `[]` znamená seznam. Osobní údaje do událostí nepatří.

Sloupec **Čeká na odběr** opisuje `#[PendingSubscribers]`: uvádí moduly, které podle kontraktu událost teprve nemají odebírat. Prázdná hodnota znamená, že třída žádný takový odklad nedeklaruje; není to seznam všech aktivních handlerů.

## Catalog

| Typ | Pole mezi obálkou | Čeká na odběr |
|---|---|---|
| `catalog.activity_rescheduled` v1 | `eventId: EventId`, `activityId: ActivityId`, `previousStartsAt: DateTimeImmutable`, `newStartsAt: DateTimeImmutable`, `important: bool` | — |
| `catalog.capacity_changed` v1 | `bucketId: CapacityBucketId`, `scope: CapacityScope`, `previousLimit: ?int`, `newLimit: ?int`, `belowOccupancy: bool` | catalog |
| `catalog.event_cancelled` v1 | `eventId: EventId`, `reason: string` | — |
| `catalog.event_completed` v1 | `eventId: EventId` | privacy |
| `catalog.event_details_changed` v1 | `eventId: EventId`, `changedFields: string[]` | catalog |
| `catalog.event_published` v1 | `eventId: EventId`, `slug: string`, `startsAt: DateTimeImmutable`, `endsAt: DateTimeImmutable`, `activityIds: ActivityId[]` | reporting, catalog |
| `catalog.form_version_published` v1 | `eventId: EventId`, `formVersionId: FormVersionId`, `previousFormVersionId: ?FormVersionId`, `addedRequiredFieldIds: string[]` | — |
| `catalog.news_published` v1 | `eventId: EventId`, `newsPostId: NewsPostId`, `broadcast: bool` | — |
| `catalog.program_item_changed` v1 | `eventId: EventId`, `programItemId: ProgramItemId`, `activityId: ?ActivityId`, `important: bool` | — |
| `catalog.registration_state_changed` v1 | `eventId: EventId`, `activityId: ?ActivityId`, `from: RegistrationState`, `to: RegistrationState` | catalog, registration |

## CheckIn a Communication

| Typ | Pole mezi obálkou | Čeká na odběr |
|---|---|---|
| `checkin.escalation_raised` v1 | `participationId: ParticipationId`, `listId: CheckInListId`, `situation: CheckInSituation` | — |
| `checkin.check_in_voided` v1 | `checkInId: CheckInId`, `participationId: ParticipationId`, `replacedBy: ?CheckInId` | reporting |
| `checkin.participation_checked_in` v1 | `checkInId: CheckInId`, `participationId: ParticipationId`, `listId: CheckInListId`, `stationId: ?CheckInStationId`, `checkedInAt: DateTimeImmutable` | reporting |
| `communication.campaign_sent` v1 | `campaignId: CampaignId`, `deliveryCount: int`, `skippedCount: int` | reporting |
| `communication.delivery_failed` v1 | `deliveryId: DeliveryId`, `campaignId: ?CampaignId`, `trigger: string`, `error: string` | — |

## Content, Identity a Legal

| Typ | Pole mezi obálkou | Čeká na odběr |
|---|---|---|
| `content.collection_published` v1 | `collectionId: CollectionId`, `eventId: EventId`, `visibility: CollectionVisibility` | — |
| `content.photo_uploaded` v1 | `photoId: PhotoId`, `collectionId: CollectionId`, `storageKey: string` | — |
| `identity.member_invited` v1 | `invitationId: InvitationId`, `role: string` | — |
| `identity.membership_changed` v1 | `membershipId: MembershipId`, `userAccountId: UserAccountId`, `previousRole: ?string`, `newRole: ?string`, `eventScope: EventId[]` | identity |
| `identity.pairrot_connected` v1 | `subtenantId: string`, `bankConnectionId: string` | catalog |
| `legal.confirmation_recorded` v1 | `confirmationId: ConfirmationId`, `requirementId: LegalRequirementId`, `versionId: DocumentVersionId`, `decision: ConsentDecision`, `participationId: ?ParticipationId`, `orderId: ?OrderId`, `channel: ConfirmationChannel` | — |
| `legal.consent_withdrawn` v1 | `confirmationId: ConfirmationId`, `requirementId: LegalRequirementId`, `requirementType: RequirementType`, `participationId: ?ParticipationId`, `orderId: OrderId` | — |
| `legal.document_version_published` v1 | `documentId: LegalDocumentId`, `versionId: DocumentVersionId`, `previousVersionId: ?DocumentVersionId`, `level: DocumentLevel`, `scopeId: Uuid`, `requiresReconfirmation: bool` | — |
| `legal.reconfirmation_requested` v1 | `requirementId: LegalRequirementId`, `versionId: DocumentVersionId`, `participationIds: ParticipationId[]`, `orderIds: OrderId[]` | — |

## Payments a Privacy

| Typ | Pole mezi obálkou | Čeká na odběr |
|---|---|---|
| `payments.cash_recorded` v1 | `orderId: OrderId`, `ledgerEntryId: LedgerEntryId`, `amount: Money`, `stationId: ?CheckInStationId` | reporting |
| `payments.integration_degraded` v1 | `organizerId: ?OrganizerId`, `integration: string`, `reason: string`, `since: DateTimeImmutable` | — |
| `payments.ledger_entry_corrected` v1 | `orderId: OrderId`, `ledgerEntryId: LedgerEntryId`, `compensatesId: LedgerEntryId`, `amount: Money` | reporting |
| `payments.order_payment_changed` v1 | `orderId: OrderId`, `previousState: ?FinancialState`, `newState: FinancialState`, `delta: Money`, `receivedTotal: Money`, `amountDue: Money`, `ledgerVersion: int` | reporting |
| `payments.overpayment_detected` v1 | `orderId: OrderId`, `overpaidAmount: Money` | — |
| `payments.payment_dispute_opened` v1 | `orderId: OrderId`, `paymentRequestId: PaymentRequestId`, `reviewCaseId: string` | — |
| `payments.payment_request_failed` v1 | `orderId: OrderId`, `paymentRequestId: PaymentRequestId`, `attempts: int`, `lastError: string` | — |
| `payments.payment_request_issued` v1 | `orderId: OrderId`, `paymentRequestId: PaymentRequestId`, `variableSymbol: string`, `amount: Money`, `dueAt: DateTimeImmutable`, `purpose: PaymentRequestPurpose` | — |
| `payments.payment_revoked` v1 | `orderId: OrderId`, `paymentRequestId: PaymentRequestId`, `amount: Money` | — |
| `payments.refund_task_opened` v1 | `refundTaskId: RefundTaskId`, `orderId: OrderId`, `amount: Money`, `reason: RefundReason` | — |
| `payments.refund_task_settled` v1 | `refundTaskId: RefundTaskId`, `orderId: OrderId`, `amount: Money` | — |
| `payments.transaction_allocated` v1 | `transactionId: BankTransactionId`, `paymentRequestId: PaymentRequestId`, `orderId: OrderId` | — |
| `payments.unmatched_transaction_received` v1 | `transactionId: BankTransactionId`, `amount: Money`, `variableSymbol: ?string`, `matchingResult: MatchingResult`, `bookedOn: DateTimeImmutable` | — |
| `privacy.data_subject_request_opened` v1 | `requestId: DataSubjectRequestId`, `type: DataSubjectRequestType`, `dueAt: DateTimeImmutable` | — |
| `privacy.person_anonymized` v1 | `personRef: PersonId`, `retentionClass: RetentionClass`, `orderIds: OrderId[]` | reporting |

## Registration

| Typ | Pole mezi obálkou | Čeká na odběr |
|---|---|---|
| `registration.answers_incomplete` v1 | `orderId: OrderId`, `participationIds: ParticipationId[]`, `formVersionId: FormVersionId` | — |
| `registration.capacity_released` v1 | `bucketIds: CapacityBucketId[]`, `seats: int`, `cause: CapacityReleaseCause` | catalog |
| `registration.late_payment_received` v1 | `orderId: OrderId`, `participationIds: ParticipationId[]`, `capacityAvailable: bool`, `autoRestore: bool` | — |
| `registration.order_amount_changed` v1 | `orderId: OrderId`, `previousAmountDue: Money`, `newAmountDue: Money`, `reason: OrderAmountChangeReason` | — |
| `registration.order_placed` v1 | `orderId: OrderId`, `orderNumber: string`, `eventId: EventId`, `paymentMethod: PaymentMethod`, `amountDue: Money`, `participationIds: ParticipationId[]`, `holdExpiresAt: ?DateTimeImmutable` | reporting |
| `registration.participation_cancelled` v1 | `orderId: OrderId`, `participationId: ParticipationId`, `by: CancelledBy`, `reason: CancellationReason` | — |
| `registration.participation_expired` v1 | `orderId: OrderId`, `participationId: ParticipationId`, `bucketIds: CapacityBucketId[]` | reporting |
| `registration.participation_requires_attention` v1 | `orderId: OrderId`, `participationId: ParticipationId`, `cause: AttentionCause` | — |
| `registration.participation_restored` v1 | `orderId: OrderId`, `participationId: ParticipationId`, `by: RestoredBy` | — |
| `registration.participations_confirmed` v1 | `orderId: OrderId`, `participationIds: ParticipationId[]`, `reason: ConfirmationReason` | registration |
| `registration.participation_transferred` v1 | `orderId: OrderId`, `previousParticipationId: ParticipationId`, `newParticipationId: ParticipationId` | legal |
| `registration.participation_variant_changed` v1 | `orderId: OrderId`, `participationId: ParticipationId`, `previousVariantId: VariantId`, `newVariantId: VariantId`, `previousActivityId: ActivityId`, `newActivityId: ActivityId` | — |
| `registration.self_service_link_requested` v1 | `orderId: OrderId`, `tokenId: SelfServiceTokenId` | — |
| `registration.waitlist_offer_expired` v1 | `offerId: WaitlistOfferId`, `entryId: WaitlistEntryId` | communication |
| `registration.waitlist_offer_made` v1 | `offerId: WaitlistOfferId`, `entryId: WaitlistEntryId`, `expiresAt: DateTimeImmutable` | communication |
