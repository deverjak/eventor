<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Tests\Integration\Architecture\ModuleCatalog;
use PHPUnit\Framework\TestCase;

/**
 * The code side of docs/architecture/06-asynchronni-svet/katalog-udalosti.md (the book is not mounted into the
 * container, so the table is copied here). Adding, renaming or reshaping an integration event means changing the
 * catalogue, this table and the subscribers together. Every event also carries the envelope fields `organizerId`
 * (first) and `occurredAt` (last), which the catalogue table leaves out.
 *
 * `subscribers` are the planned ones; the live `#[PendingSubscribers]` list may only shrink below them (P1 events are
 * marked in the class docblock). Deviations from the catalogue payload are documented in docs/implementation/contracts.md.
 */
final class EventCatalogConsistencyTest extends TestCase
{
    private const array CATALOGUE = [
        'catalog.activity_rescheduled' => ['class' => \Eventor\Catalog\Contract\Event\ActivityRescheduled::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'activityId', 'previousStartsAt', 'newStartsAt', 'important'], 'subscribers' => ['communication', 'checkin']],
        'catalog.capacity_changed' => ['class' => \Eventor\Catalog\Contract\Event\CapacityChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['bucketId', 'scope', 'previousLimit', 'newLimit', 'belowOccupancy'], 'subscribers' => ['registration', 'catalog']],
        'catalog.event_cancelled' => ['class' => \Eventor\Catalog\Contract\Event\EventCancelled::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'reason'], 'subscribers' => ['registration', 'communication', 'checkin']],
        'catalog.event_completed' => ['class' => \Eventor\Catalog\Contract\Event\EventCompleted::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId'], 'subscribers' => ['communication', 'privacy']],
        'catalog.event_details_changed' => ['class' => \Eventor\Catalog\Contract\Event\EventDetailsChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'changedFields'], 'subscribers' => ['catalog']],
        'catalog.event_published' => ['class' => \Eventor\Catalog\Contract\Event\EventPublished::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'slug', 'startsAt', 'endsAt', 'activityIds'], 'subscribers' => ['reporting', 'catalog', 'checkin']],
        'catalog.form_version_published' => ['class' => \Eventor\Catalog\Contract\Event\FormVersionPublished::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'formVersionId', 'previousFormVersionId', 'addedRequiredFieldIds'], 'subscribers' => ['registration']],
        'catalog.news_published' => ['class' => \Eventor\Catalog\Contract\Event\NewsPublished::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'newsPostId', 'broadcast'], 'subscribers' => ['communication']],
        'catalog.program_item_changed' => ['class' => \Eventor\Catalog\Contract\Event\ProgramItemChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'programItemId', 'activityId', 'important'], 'subscribers' => ['communication']],
        'catalog.registration_state_changed' => ['class' => \Eventor\Catalog\Contract\Event\RegistrationStateChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['eventId', 'activityId', 'from', 'to'], 'subscribers' => ['catalog', 'registration']],
        'checkin.check_in_voided' => ['class' => \Eventor\CheckIn\Contract\Event\CheckInVoided::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['checkInId', 'participationId', 'replacedBy'], 'subscribers' => ['reporting']],
        'checkin.escalation_raised' => ['class' => \Eventor\CheckIn\Contract\Event\EscalationRaised::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['participationId', 'listId', 'situation'], 'subscribers' => ['attention']],
        'checkin.participation_checked_in' => ['class' => \Eventor\CheckIn\Contract\Event\ParticipationCheckedIn::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['checkInId', 'participationId', 'listId', 'stationId', 'checkedInAt'], 'subscribers' => ['reporting']],
        'communication.campaign_sent' => ['class' => \Eventor\Communication\Contract\Event\CampaignSent::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['campaignId', 'deliveryCount', 'skippedCount'], 'subscribers' => ['reporting']],
        'communication.delivery_failed' => ['class' => \Eventor\Communication\Contract\Event\DeliveryFailed::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['deliveryId', 'campaignId', 'trigger', 'error'], 'subscribers' => ['attention']],
        'content.collection_published' => ['class' => \Eventor\Content\Contract\Event\CollectionPublished::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['collectionId', 'eventId', 'visibility'], 'subscribers' => ['attention']],
        'content.photo_uploaded' => ['class' => \Eventor\Content\Contract\Event\PhotoUploaded::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['photoId', 'collectionId', 'storageKey'], 'subscribers' => ['content']],
        'identity.member_invited' => ['class' => \Eventor\Identity\Contract\Event\MemberInvited::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['invitationId', 'role'], 'subscribers' => ['communication']],
        'identity.membership_changed' => ['class' => \Eventor\Identity\Contract\Event\MembershipChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['membershipId', 'userAccountId', 'previousRole', 'newRole', 'eventScope'], 'subscribers' => ['identity']],
        'identity.pairrot_connected' => ['class' => \Eventor\Identity\Contract\Event\PairrotConnected::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['subtenantId', 'bankConnectionId'], 'subscribers' => ['catalog']],
        'legal.confirmation_recorded' => ['class' => \Eventor\Legal\Contract\Event\ConfirmationRecorded::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['confirmationId', 'requirementId', 'versionId', 'decision', 'participationId', 'orderId', 'channel'], 'subscribers' => ['registration']],
        'legal.consent_withdrawn' => ['class' => \Eventor\Legal\Contract\Event\ConsentWithdrawn::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['confirmationId', 'requirementId', 'requirementType', 'participationId', 'orderId'], 'subscribers' => ['attention', 'communication']],
        'legal.document_version_published' => ['class' => \Eventor\Legal\Contract\Event\DocumentVersionPublished::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['documentId', 'versionId', 'previousVersionId', 'level', 'scopeId', 'requiresReconfirmation'], 'subscribers' => ['legal', 'communication']],
        'legal.reconfirmation_requested' => ['class' => \Eventor\Legal\Contract\Event\ReconfirmationRequested::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['requirementId', 'versionId', 'participationIds', 'orderIds'], 'subscribers' => ['communication', 'registration']],
        'payments.cash_recorded' => ['class' => \Eventor\Payments\Contract\Event\CashRecorded::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'ledgerEntryId', 'amount', 'stationId'], 'subscribers' => ['reporting']],
        'payments.integration_degraded' => ['class' => \Eventor\Payments\Contract\Event\IntegrationDegraded::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['integration', 'reason', 'since'], 'subscribers' => ['attention']],
        'payments.ledger_entry_corrected' => ['class' => \Eventor\Payments\Contract\Event\LedgerEntryCorrected::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'ledgerEntryId', 'compensatesId', 'amount'], 'subscribers' => ['reporting']],
        'payments.order_payment_changed' => ['class' => \Eventor\Payments\Contract\Event\OrderPaymentChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'previousState', 'newState', 'delta', 'receivedTotal', 'amountDue', 'ledgerVersion'], 'subscribers' => ['registration', 'communication', 'reporting']],
        'payments.overpayment_detected' => ['class' => \Eventor\Payments\Contract\Event\OverpaymentDetected::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'overpaidAmount'], 'subscribers' => ['attention', 'payments']],
        'payments.payment_dispute_opened' => ['class' => \Eventor\Payments\Contract\Event\PaymentDisputeOpened::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'paymentRequestId', 'reviewCaseId'], 'subscribers' => ['attention']],
        'payments.payment_request_failed' => ['class' => \Eventor\Payments\Contract\Event\PaymentRequestFailed::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'paymentRequestId', 'attempts', 'lastError'], 'subscribers' => ['attention']],
        'payments.payment_request_issued' => ['class' => \Eventor\Payments\Contract\Event\PaymentRequestIssued::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'paymentRequestId', 'variableSymbol', 'amount', 'dueAt', 'purpose'], 'subscribers' => ['communication', 'registration']],
        'payments.payment_revoked' => ['class' => \Eventor\Payments\Contract\Event\PaymentRevoked::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'paymentRequestId', 'amount'], 'subscribers' => ['attention']],
        'payments.refund_task_opened' => ['class' => \Eventor\Payments\Contract\Event\RefundTaskOpened::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['refundTaskId', 'orderId', 'amount', 'reason'], 'subscribers' => ['attention']],
        'payments.refund_task_settled' => ['class' => \Eventor\Payments\Contract\Event\RefundTaskSettled::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['refundTaskId', 'orderId', 'amount'], 'subscribers' => ['attention', 'communication']],
        'payments.transaction_allocated' => ['class' => \Eventor\Payments\Contract\Event\TransactionAllocated::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['transactionId', 'paymentRequestId', 'orderId'], 'subscribers' => ['attention']],
        'payments.unmatched_transaction_received' => ['class' => \Eventor\Payments\Contract\Event\UnmatchedTransactionReceived::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['transactionId', 'amount', 'variableSymbol', 'matchingResult', 'bookedOn'], 'subscribers' => ['attention']],
        'privacy.data_subject_request_opened' => ['class' => \Eventor\Privacy\Contract\Event\DataSubjectRequestOpened::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['requestId', 'type', 'dueAt'], 'subscribers' => ['attention']],
        'privacy.person_anonymized' => ['class' => \Eventor\Privacy\Contract\Event\PersonAnonymized::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['personRef', 'retentionClass', 'orderIds'], 'subscribers' => ['reporting']],
        'registration.answers_incomplete' => ['class' => \Eventor\Registration\Contract\Event\AnswersIncomplete::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationIds', 'formVersionId'], 'subscribers' => ['communication']],
        'registration.capacity_released' => ['class' => \Eventor\Registration\Contract\Event\CapacityReleased::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['bucketIds', 'seats', 'cause'], 'subscribers' => ['registration', 'catalog']],
        'registration.late_payment_received' => ['class' => \Eventor\Registration\Contract\Event\LatePaymentReceived::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationIds', 'capacityAvailable', 'autoRestore'], 'subscribers' => ['attention']],
        'registration.order_amount_changed' => ['class' => \Eventor\Registration\Contract\Event\OrderAmountChanged::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'previousAmountDue', 'newAmountDue', 'reason'], 'subscribers' => ['payments']],
        'registration.order_placed' => ['class' => \Eventor\Registration\Contract\Event\OrderPlaced::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'orderNumber', 'eventId', 'paymentMethod', 'amountDue', 'participationIds', 'holdExpiresAt'], 'subscribers' => ['payments', 'communication', 'reporting']],
        'registration.participation_cancelled' => ['class' => \Eventor\Registration\Contract\Event\ParticipationCancelled::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationId', 'by', 'reason'], 'subscribers' => ['communication', 'checkin']],
        'registration.participation_expired' => ['class' => \Eventor\Registration\Contract\Event\ParticipationExpired::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationId', 'bucketIds'], 'subscribers' => ['communication', 'reporting']],
        'registration.participation_requires_attention' => ['class' => \Eventor\Registration\Contract\Event\ParticipationRequiresAttention::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationId', 'cause'], 'subscribers' => ['attention']],
        'registration.participation_restored' => ['class' => \Eventor\Registration\Contract\Event\ParticipationRestored::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationId', 'by'], 'subscribers' => ['communication', 'attention']],
        'registration.participation_transferred' => ['class' => \Eventor\Registration\Contract\Event\ParticipationTransferred::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['orderId', 'previousParticipationId', 'newParticipationId'], 'subscribers' => ['communication', 'legal']],
        'registration.participation_variant_changed' => ['class' => \Eventor\Registration\Contract\Event\ParticipationVariantChanged::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['orderId', 'participationId', 'previousVariantId', 'newVariantId', 'previousActivityId', 'newActivityId'], 'subscribers' => ['communication', 'checkin']],
        'registration.participations_confirmed' => ['class' => \Eventor\Registration\Contract\Event\ParticipationsConfirmed::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'participationIds', 'reason'], 'subscribers' => ['communication', 'checkin', 'registration']],
        'registration.self_service_link_requested' => ['class' => \Eventor\Registration\Contract\Event\SelfServiceLinkRequested::class, 'version' => 1, 'priority' => 'P0', 'fields' => ['orderId', 'tokenId'], 'subscribers' => ['communication']],
        'registration.waitlist_offer_expired' => ['class' => \Eventor\Registration\Contract\Event\WaitlistOfferExpired::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['offerId', 'entryId'], 'subscribers' => ['communication']],
        'registration.waitlist_offer_made' => ['class' => \Eventor\Registration\Contract\Event\WaitlistOfferMade::class, 'version' => 1, 'priority' => 'P1', 'fields' => ['offerId', 'entryId', 'expiresAt'], 'subscribers' => ['communication']],
    ];

    public function test_every_catalogue_event_exists_and_every_contract_event_is_in_the_catalogue(): void
    {
        $inCode = [];
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            $inCode[(new \ReflectionClass($class))->getAttributes(IntegrationEvent::class)[0]->newInstance()->name] = $class;
        }

        self::assertEqualsCanonicalizing(array_keys(self::CATALOGUE), array_keys($inCode));
        foreach (self::CATALOGUE as $name => $expected) {
            self::assertSame($expected['class'], $inCode[$name], $name);
        }
    }

    public function test_versions_payload_fields_and_envelope_match_the_catalogue(): void
    {
        foreach (self::CATALOGUE as $name => $expected) {
            $reflection = new \ReflectionClass($expected['class']);
            self::assertTrue($reflection->isFinal() && $reflection->isReadOnly(), "$name must be final readonly");
            self::assertSame($expected['version'], $reflection->getAttributes(IntegrationEvent::class)[0]->newInstance()->version, "$name version");

            $properties = array_map(static fn(\ReflectionParameter $p): string => $p->getName(), $reflection->getConstructor()?->getParameters() ?? []);
            self::assertSame('organizerId', $properties[0] ?? null, "$name: the tenant comes first");
            self::assertSame('occurredAt', $properties[\count($properties) - 1] ?? null, "$name: the time comes last");
            self::assertSame($expected['fields'], \array_slice($properties, 1, -1), "$name payload differs from the catalogue");
        }
    }

    public function test_pending_subscribers_never_exceed_the_plan_and_every_event_states_its_priority(): void
    {
        foreach (self::CATALOGUE as $name => $expected) {
            $reflection = new \ReflectionClass($expected['class']);
            $pending = $reflection->getAttributes(PendingSubscribers::class);
            if ([] !== $pending) {
                self::assertSame([], array_values(array_diff($pending[0]->newInstance()->modules, $expected['subscribers'])), "$name lists a subscriber that is not in the plan");
            }

            self::assertStringContainsString($expected['priority'].'.', (string) $reflection->getDocComment(), "$name docblock must state its priority");
        }
    }
}
