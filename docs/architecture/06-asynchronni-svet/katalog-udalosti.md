[Architektura](../README.md) · [06 Asynchronní svět](README.md) · **Katalog událostí**

# Katalog integračních událostí

Tento katalog je kontrakt mezi moduly. Obsahuje jen **integrační události** (`backend/src/<Modul>/Contract/Event/`). Doménové události agregátů zůstávají uvnitř modulu a v katalogu nejsou. Odběratel smí spoléhat jen na pole uvedená zde. `IntegrationEventRoutingPass` při kompilaci ověřuje, že každá třída má `#[IntegrationEvent]` a odběratele (nebo `#[NoSubscribers]`), a test `EventCatalogConsistencyTest` porovnává tuto tabulku se třídami.

## Pojmenování

- **Typ:** `<modul>.<fakt_v_minulém_čase>` v `snake_case`, například `registration.order_placed`, `payments.order_payment_changed`. Třída má stejné jméno v PascalCase (`OrderPlaced`) a leží v `Contract/Event` produkujícího modulu, protože ji importují odběratelé.
- **Fakt, ne příkaz:** `participation_cancelled`, nikoli `cancel_participation`. Událost nikoho neúkoluje. Odběratel se rozhoduje sám.
- **Jeden producent:** událost publikuje výhradně modul, kterému patří agregát.

## Zpráva

Integrační událost je `final readonly` třída, kterou Messenger serializuje jako JSON (Serializer). Metadata nejsou v těle, ale ve stampech obálky:

```php
#[IntegrationEvent(name: 'registration.order_placed', version: 1)]
final readonly class OrderPlaced
{
    public function __construct(
        public Uuid $eventId,          // UUIDv7, idempotenční klíč pro odběratele
        public Uuid $orderId,
        public Uuid $organizerId,
        public Money $amountDue,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
// stampy: TenantStamp(organizerId), ActorStamp(type, id), CorrelationStamp(correlationId)
```

**Payload nesmí obsahovat osobní údaje** (jména, e-maily, telefony, datum narození, odpovědi formuláře). Nese jen identifikátory, částky, stavy a časy. Odběratel, který osobní údaje potřebuje (Communication pro e-mail), si je načte přes `Contract/` vlastníka v okamžiku zpracování. Díky tomu:

- anonymizace (PRV-04) nemusí sahat do front ani do `failed` transportu,
- logy doručení jsou bezpečné.

## Verzování

| Změna | Postup |
|---|---|
| přidání volitelného pole | stejná verze. Odběratelé neznámá pole ignorují. |
| přejmenování, odebrání, změna významu pole | nová verze (`version: 2`). Producent po přechodnou dobu emituje obě verze (dvě třídy, `OrderPlacedV2`), odběratelé přecházejí jednotlivě a poté se v1 odstraní. |
| změna typu (rozdělení události) | nový typ. Starý se označí `#[Deprecated]` a compiler pass hlásí varování, dokud má odběratele. |

Zprávy staré verze, které ještě čekají ve frontě nebo ve `failed`, deserializuje `UpcasterChain` (tagované `EventUpcaster`) dekorující serializer Messengeru. V praxi jde o hodiny, fronty se po zpracování mažou.

## Události

Sloupec *Odběratelé* uvádí reakce (`modul.účel`). Prefix určuje transport (`payments.*` → `payments_events`). Když má jeden modul na událost víc reakcí, implementuje je **jeden handler**, který dispatchne samostatné commandy (každý s vlastní transakcí). Audit odběratelem není: auditní záznam vzniká synchronně v transakci příkazu.

### Catalog

| Typ | Payload | Odběratelé |
|---|---|---|
| `catalog.event_published` v1 | `eventId`, `slug`, `startsAt`, `endsAt`, `activityIds[]` | `reporting.refresh_event_summary`, `catalog.warm_public_cache` |
| `catalog.event_details_changed` v1 | `eventId`, `changedFields[]` | `catalog.invalidate_public_cache` |
| `catalog.event_cancelled` v1 | `eventId`, `reason` | `registration.cancel_orders_on_event_cancelled`, `communication.notify_event_cancelled`, `checkin.close_lists` |
| `catalog.event_completed` v1 | `eventId` | `communication.enable_post_event_messages`, `privacy.schedule_retention` (P1) |
| `catalog.registration_state_changed` v1 | `eventId`, `activityId?`, `from`, `to` | `catalog.invalidate_public_cache`, `registration.notify_waitlist_closed` (P1) |
| `catalog.activity_rescheduled` v1 | `eventId`, `activityId`, `previousStartsAt`, `newStartsAt`, `important` | `communication.propose_activity_change_campaign`, `checkin.update_list_window` |
| `catalog.capacity_changed` v1 | `bucketId`, `scope`, `previousLimit`, `newLimit`, `belowOccupancy` | `registration.capacity_released` (při navýšení), `catalog.invalidate_public_cache` |
| `catalog.form_version_published` v1 | `eventId`, `formVersionId`, `previousFormVersionId`, `addedRequiredFieldIds[]` | `registration.request_missing_answers` |
| `catalog.program_item_changed` v1 | `eventId`, `programItemId`, `activityId?`, `important` | `communication.propose_program_change_campaign` |
| `catalog.news_published` v1 | `eventId`, `newsPostId`, `broadcast` | `communication.broadcast_news` (pokud `broadcast`) |

### Legal

| Typ | Payload | Odběratelé |
|---|---|---|
| `legal.document_version_published` v1 | `documentId`, `versionId`, `previousVersionId?`, `level`, `scopeId`, `requiresReconfirmation` | `legal.request_reconfirmation` (pokud příznak), `communication.notify_document_change` |
| `legal.reconfirmation_requested` v1 | `requirementId`, `versionId`, `participationIds[]`, `orderIds[]` | `communication.send_reconfirmation_requests`, `registration.mark_legal_incomplete` |
| `legal.confirmation_recorded` v1 | `confirmationId`, `requirementId`, `versionId`, `decision`, `participationId?`, `orderId?`, `channel` | `registration.recompute_legal_state` |
| `legal.consent_withdrawn` v1 | `confirmationId`, `requirementId`, `requirementType`, `participationId?`, `orderId` | `attention.notify_consent_withdrawn`, `attention.open_media_review` (typ `LIKENESS`), `communication.exclude_from_marketing` |

### Registration

| Typ | Payload | Odběratelé |
|---|---|---|
| `registration.order_placed` v1 | `orderId`, `orderNumber`, `eventId`, `paymentMethod`, `amountDue{amount,currency}`, `participationIds[]`, `holdExpiresAt?` | `payments.create_payment_request`, `communication.send_order_received`, `reporting.refresh_event_summary` |
| `registration.participations_confirmed` v1 | `orderId`, `participationIds[]`, `reason` (`paid` / `cash` / `free` / `manual` / `restored`) | `communication.send_participation_confirmed`, `checkin.refresh_list`, `registration.close_waitlist_offer` |
| `registration.participation_expired` v1 | `orderId`, `participationId`, `bucketIds[]` | `communication.send_reservation_expired`, `reporting.refresh_event_summary` (platební požadavek v Pairrotu se záměrně neruší, aby pozdní platba spárovala) |
| `registration.participation_cancelled` v1 | `orderId`, `participationId`, `by` (`participant` / `organizer` / `system`), `reason` | `communication.send_participation_cancelled`, `checkin.refresh_list` |
| `registration.participation_requires_attention` v1 | `orderId`, `participationId`, `cause` (`late_payment` / `payment_on_cancelled`) | `attention.open_item` |
| `registration.late_payment_received` v1 | `orderId`, `participationIds[]`, `capacityAvailable`, `autoRestore` | `attention.open_item` (když se neobnoví automaticky) |
| `registration.participation_restored` v1 | `orderId`, `participationId`, `by` (`system` / `user`) | `communication.send_participation_confirmed`, `attention.close_item` |
| `registration.participation_variant_changed` v1 | `orderId`, `participationId`, `previousVariantId`, `newVariantId`, `previousActivityId`, `newActivityId` | `communication.send_participation_changed`, `checkin.refresh_list` |
| `registration.participation_transferred` v1 (P1) | `orderId`, `previousParticipationId`, `newParticipationId` | `communication.send_transfer_confirmation`, `legal.request_new_confirmations` |
| `registration.order_amount_changed` v1 | `orderId`, `previousAmountDue`, `newAmountDue`, `reason` | `payments.recompute_financial_state`, `payments.adjust_payment_request` (nic nezaplaceno → zrušit a nový pokus; něco zaplaceno a dluh vzrostl → doplňkový požadavek) |
| `registration.capacity_released` v1 | `bucketIds[]`, `seats`, `cause` (`cancelled` / `expired` / `limit_raised` / `offer_expired`) | `registration.offer_seat_to_waitlist` (P1), `catalog.invalidate_public_cache` |
| `registration.answers_incomplete` v1 | `orderId`, `participationIds[]`, `formVersionId` | `communication.send_missing_answers_request` |
| `registration.self_service_link_requested` v1 | `orderId`, `tokenId` | `communication.send_self_service_link` (REG-11) |
| `registration.waitlist_offer_made` v1 (P1) | `offerId`, `entryId`, `expiresAt` | `communication.send_waitlist_offer` |
| `registration.waitlist_offer_expired` v1 (P1) | `offerId`, `entryId` | `communication.send_waitlist_offer_expired` |

### Payments

| Typ | Payload | Odběratelé |
|---|---|---|
| `payments.payment_request_issued` v1 | `orderId`, `paymentRequestId`, `variableSymbol`, `amount`, `dueAt`, `purpose` (`initial` / `retry` / `supplementary`) | `communication.send_payment_instructions`, `registration.extend_hold_if_late` |
| `payments.payment_request_failed` v1 | `orderId`, `paymentRequestId`, `attempts`, `lastError` | `attention.open_item` (`payment_request_issue_failed`) |
| `payments.payment_revoked` v1 | `orderId`, `paymentRequestId`, `amount` | `attention.open_item` (`payment_revoked`) |
| `payments.integration_degraded` v1 | `integration`, `reason`, `since` | `attention.open_item` (`integration_degraded`) |
| `payments.order_payment_changed` v1 | `orderId`, `previousState`, `newState`, `delta`, `receivedTotal`, `amountDue`, `ledgerVersion` | `registration.confirm_participations`, `communication.send_payment_status_message` (částečná úhrada, potvrzení), `reporting.refresh_finance` |
| `payments.overpayment_detected` v1 | `orderId`, `overpaidAmount` | `attention.open_item`, `payments.open_refund_task` (podle nastavení) |
| `payments.refund_task_opened` v1 | `refundTaskId`, `orderId`, `amount`, `reason` | `attention.open_item` |
| `payments.refund_task_settled` v1 | `refundTaskId`, `orderId`, `amount` | `attention.close_item`, `communication.send_refund_settled` |
| `payments.cash_recorded` v1 | `orderId`, `ledgerEntryId`, `amount`, `stationId?` | `reporting.refresh_cash` |
| `payments.ledger_entry_corrected` v1 | `orderId`, `ledgerEntryId`, `compensatesId`, `amount` | `reporting.refresh_finance` |
| `payments.unmatched_transaction_received` v1 | `transactionId`, `amount`, `variableSymbol?`, `matchingResult`, `bookedOn` | `attention.open_reconciliation_item` |
| `payments.transaction_allocated` v1 | `transactionId`, `paymentRequestId`, `orderId` | `attention.close_item` |
| `payments.payment_dispute_opened` v1 | `orderId`, `paymentRequestId`, `reviewCaseId` | `attention.open_item` |

### CheckIn

| Typ | Payload | Odběratelé |
|---|---|---|
| `checkin.participation_checked_in` v1 | `checkInId`, `participationId`, `listId`, `stationId?`, `checkedInAt` | `reporting.refresh_check_in` |
| `checkin.check_in_voided` v1 | `checkInId`, `participationId`, `replacedBy?` | `reporting.refresh_check_in` |
| `checkin.escalation_raised` v1 | `participationId`, `listId`, `situation` (CHK-09) | `attention.open_item` |

### Communication

| Typ | Payload | Odběratelé |
|---|---|---|
| `communication.campaign_sent` v1 | `campaignId`, `deliveryCount`, `skippedCount` | `reporting.refresh_communication` |
| `communication.delivery_failed` v1 | `deliveryId`, `campaignId?`, `trigger`, `error` | `attention.aggregate_delivery_failures` |

### Content

| Typ | Payload | Odběratelé |
|---|---|---|
| `content.photo_uploaded` v1 | `photoId`, `collectionId`, `storageKey` | `content.generate_thumbnails` |
| `content.collection_published` v1 | `collectionId`, `eventId`, `visibility` | `attention.warn_missing_likeness_consents` (P1, POST-06) |

### Privacy (P1)

| Typ | Payload | Odběratelé |
|---|---|---|
| `privacy.data_subject_request_opened` v1 | `requestId`, `type`, `dueAt` | `attention.open_item` |
| `privacy.person_anonymized` v1 | `personRef`, `retentionClass`, `orderIds[]` | `reporting.refresh_event_summary` |

### Identity

| Typ | Payload | Odběratelé |
|---|---|---|
| `identity.member_invited` v1 | `invitationId`, `organizerId`, `role` | `communication.send_member_invitation` |
| `identity.membership_changed` v1 | `membershipId`, `previousRole?`, `newRole?`, `eventScope[]` | `identity.invalidate_permission_cache` |
| `identity.pairrot_connected` v1 | `organizerId`, `subtenantId`, `bankConnectionId` | `catalog.recheck_publishability` |

### Technické

Dead letter není integrační událost. `DeadLetterListener` (na `WorkerMessageFailedEvent`, když `willRetry()` vrátí `false`) dispatchne rovnou command `OpenAttentionItem(type: outbox_dead_letter)` na `command.bus`. Selhání tohoto commandu se jen loguje, aby nevznikla smyčka.
