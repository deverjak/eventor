<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Attention;

use Eventor\Attention\Contract\AttentionQueue;
use Eventor\CheckIn\Contract\Event\EscalationRaised;
use Eventor\Communication\Contract\Event\DeliveryFailed;
use Eventor\Content\Contract\Event\CollectionPublished;
use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Eventor\Payments\Contract\Event\IntegrationDegraded;
use Eventor\Payments\Contract\Event\OverpaymentDetected;
use Eventor\Payments\Contract\Event\PaymentDisputeOpened;
use Eventor\Payments\Contract\Event\PaymentRequestFailed;
use Eventor\Payments\Contract\Event\PaymentRevoked;
use Eventor\Payments\Contract\Event\RefundTaskOpened;
use Eventor\Payments\Contract\Event\RefundTaskSettled;
use Eventor\Payments\Contract\Event\TransactionAllocated;
use Eventor\Payments\Contract\Event\UnmatchedTransactionReceived;
use Eventor\Privacy\Contract\Event\DataSubjectRequestOpened;
use Eventor\Registration\Contract\Event\LatePaymentReceived;
use Eventor\Registration\Contract\Event\ParticipationRequiresAttention;
use Eventor\Registration\Contract\Event\ParticipationRestored;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Tests\Support\DrainResult;
use Eventor\Tests\Support\EventSampler;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Every catalogue event that plans an `attention` subscriber reaches the queue through the `attention_events`
 * transport and opens (or closes) exactly one item per cause.
 */
final class AttentionSubscribersTest extends IntegrationTestCase
{
    private OrganizerId $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = OrganizerId::fromString($this->createOwner('att@example.test', 'att-club')['organizerId']);
    }

    public function test_every_event_opens_one_item_of_the_expected_type_and_a_redelivery_adds_none(): void
    {
        $org = $this->organizer;
        $participation = ParticipationId::generate();
        $order = OrderId::generate();
        $transaction = BankTransactionId::generate();
        $refund = RefundTaskId::generate();

        /** @var list<array{object, AttentionType, string, ?string}> $cases event, type, subject type, subject id (null = any) */
        $cases = [
            [EventSampler::sample(LatePaymentReceived::class, ['organizerId' => $org, 'orderId' => $order, 'participationIds' => [$participation], 'autoRestore' => false]), AttentionType::LatePayment, 'participation', $participation->toString()],
            [EventSampler::sample(ParticipationRequiresAttention::class, ['organizerId' => $org, 'orderId' => $order, 'participationId' => ParticipationId::generate()]), AttentionType::LatePayment, 'participation', null],
            [EventSampler::sample(OverpaymentDetected::class, ['organizerId' => $org, 'orderId' => $order]), AttentionType::Overpayment, 'order', $order->toString()],
            [EventSampler::sample(UnmatchedTransactionReceived::class, ['organizerId' => $org, 'transactionId' => $transaction]), AttentionType::UnmatchedPayment, 'bank_transaction', $transaction->toString()],
            [EventSampler::sample(PaymentRevoked::class, ['organizerId' => $org, 'orderId' => $order]), AttentionType::PaymentRevoked, 'payment_request', null],
            [EventSampler::sample(PaymentRequestFailed::class, ['organizerId' => $org, 'orderId' => $order]), AttentionType::PaymentRequestIssueFailed, 'payment_request', null],
            [EventSampler::sample(PaymentDisputeOpened::class, ['organizerId' => $org, 'orderId' => $order]), AttentionType::PairrotReview, 'review_case', null],
            [EventSampler::sample(IntegrationDegraded::class, ['organizerId' => $org]), AttentionType::IntegrationDegraded, 'integration', null],
            [EventSampler::sample(RefundTaskOpened::class, ['organizerId' => $org, 'orderId' => $order, 'refundTaskId' => $refund]), AttentionType::RefundTask, 'refund_task', $refund->toString()],
            [EventSampler::sample(ConsentWithdrawn::class, ['organizerId' => $org, 'orderId' => $order]), AttentionType::ConsentWithdrawn, 'confirmation', null],
            [EventSampler::sample(DeliveryFailed::class, ['organizerId' => $org]), AttentionType::DeliveryFailed, 'campaign', null],
            [EventSampler::sample(EscalationRaised::class, ['organizerId' => $org]), AttentionType::MissingConfirmation, 'participation', null],
            [EventSampler::sample(CollectionPublished::class, ['organizerId' => $org]), AttentionType::MissingConfirmation, 'collection', null],
            [EventSampler::sample(DataSubjectRequestOpened::class, ['organizerId' => $org]), AttentionType::CancellationRequest, 'data_subject_request', null],
        ];

        foreach ($cases as [$event]) {
            $this->publish($event);
            $this->publish($event); // at-least-once delivery
        }
        $result = $this->drainQueues();

        self::assertSame(0, $result->failed, 'no subscriber may fail');
        self::assertSame(\count($cases), $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE state = 'open' AND type <> 'outbox_dead_letter'"), 'one item per cause');
        foreach ($cases as [, $type, $subjectType, $subjectId]) {
            $row = $this->dbRows(
                'SELECT subject_id FROM attention.attention_item WHERE organizer_id = ? AND type = ? AND subject_type = ?',
                [$this->organizer->toString(), $type->value, $subjectType],
            );
            self::assertNotEmpty($row, "$type->value / $subjectType");
            if (null !== $subjectId) {
                self::assertContains($subjectId, array_column($row, 'subject_id'));
            }
        }
    }

    public function test_the_catalogue_events_of_attention_have_no_pending_subscriber_left(): void
    {
        $remaining = [];
        foreach ([LatePaymentReceived::class, ParticipationRequiresAttention::class, ParticipationRestored::class, OverpaymentDetected::class, UnmatchedTransactionReceived::class, TransactionAllocated::class, PaymentRevoked::class, PaymentRequestFailed::class, PaymentDisputeOpened::class, IntegrationDegraded::class, RefundTaskOpened::class, RefundTaskSettled::class, ConsentWithdrawn::class, DeliveryFailed::class, EscalationRaised::class, CollectionPublished::class, DataSubjectRequestOpened::class] as $class) {
            foreach ((new \ReflectionClass($class))->getAttributes(\Eventor\Shared\Domain\Messaging\PendingSubscribers::class) as $attribute) {
                if (\in_array('attention', $attribute->newInstance()->modules, true)) {
                    $remaining[] = $class;
                }
            }
        }

        self::assertSame([], $remaining);
    }

    public function test_the_event_of_the_collection_scopes_the_item(): void
    {
        $event = EventId::generate();
        $this->publish(EventSampler::sample(CollectionPublished::class, ['organizerId' => $this->organizer, 'eventId' => $event]));
        $this->drainQueues();

        self::assertSame(1, $this->dbInt('SELECT count(*) FROM attention.attention_item WHERE event_id = ?', [$event->toString()]));
        $summary = $this->service(AttentionQueue::class)->summary($this->organizer, $event);
        self::assertSame(1, $summary->open);
        self::assertSame(['missing_confirmation' => 1], $summary->byType);
        self::assertSame(0, $this->service(AttentionQueue::class)->summary($this->organizer, EventId::generate())->open);
    }

    public function test_late_payment_is_not_raised_when_registration_restores_automatically(): void
    {
        $this->publish(EventSampler::sample(LatePaymentReceived::class, ['organizerId' => $this->organizer, 'autoRestore' => true, 'capacityAvailable' => true]));
        $this->drainQueues();

        self::assertSame(0, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'late_payment'"));
    }

    public function test_a_late_payment_for_several_participations_opens_one_item_each(): void
    {
        $this->publish(EventSampler::sample(LatePaymentReceived::class, ['organizerId' => $this->organizer, 'autoRestore' => false, 'participationIds' => [ParticipationId::generate(), ParticipationId::generate()]]));
        $this->drainQueues();

        self::assertSame(2, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'late_payment'"));
    }

    public function test_restoring_the_participation_closes_its_late_payment_item_and_audits_it(): void
    {
        $participation = ParticipationId::generate();
        $order = OrderId::generate();
        $this->publish(EventSampler::sample(LatePaymentReceived::class, ['organizerId' => $this->organizer, 'orderId' => $order, 'participationIds' => [$participation], 'autoRestore' => false]));
        $this->drainQueues();
        $id = $this->dbString("SELECT id FROM attention.attention_item WHERE type = 'late_payment'");

        $this->publish(EventSampler::sample(ParticipationRestored::class, ['organizerId' => $this->organizer, 'orderId' => $order, 'participationId' => $participation]));
        $this->drainQueues();

        $row = $this->dbRow('SELECT state, resolution, resolved_at FROM attention.attention_item WHERE id = ?', [$id]);
        self::assertSame('resolved', $row['state']);
        self::assertSame('auto', $row['resolution']);
        self::assertNotNull($row['resolved_at']);
        self::assertSame(
            ['attention.opened', 'attention.resolved'],
            $this->dbColumn("SELECT action FROM audit.audit_entry WHERE entity_type = 'attention_item' AND entity_id = ? ORDER BY occurred_at, id", [$id]),
        );

        // A later payment for the same participation opens a new item.
        $this->publish(EventSampler::sample(LatePaymentReceived::class, ['organizerId' => $this->organizer, 'orderId' => $order, 'participationIds' => [$participation], 'autoRestore' => false]));
        $this->drainQueues();
        self::assertSame(2, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'late_payment'"));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE type = 'late_payment' AND state = 'open'"));
    }

    public function test_allocating_the_transaction_closes_the_unmatched_item(): void
    {
        $transaction = BankTransactionId::generate();
        $this->publish(EventSampler::sample(UnmatchedTransactionReceived::class, ['organizerId' => $this->organizer, 'transactionId' => $transaction]));
        $this->drainQueues();
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE state = 'open' AND type = 'unmatched_payment'"));

        $this->publish(EventSampler::sample(TransactionAllocated::class, ['organizerId' => $this->organizer, 'transactionId' => $transaction]));
        $this->drainQueues();

        self::assertSame('resolved', $this->dbString("SELECT state FROM attention.attention_item WHERE type = 'unmatched_payment'"));
    }

    public function test_settling_the_refund_closes_the_refund_item_even_when_acknowledged(): void
    {
        $refund = RefundTaskId::generate();
        $this->publish(EventSampler::sample(RefundTaskOpened::class, ['organizerId' => $this->organizer, 'refundTaskId' => $refund]));
        $this->drainQueues();
        $this->connection()->executeStatement("UPDATE attention.attention_item SET state = 'acknowledged' WHERE type = 'refund_task'");

        $this->publish(EventSampler::sample(RefundTaskSettled::class, ['organizerId' => $this->organizer, 'refundTaskId' => $refund]));
        $this->drainQueues();

        self::assertSame('resolved', $this->dbString("SELECT state FROM attention.attention_item WHERE type = 'refund_task'"));
    }

    public function test_closing_something_that_is_not_open_is_harmless(): void
    {
        $this->publish(EventSampler::sample(TransactionAllocated::class, ['organizerId' => $this->organizer]));
        $result = $this->drainQueues();

        self::assertSame(0, $result->failed);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM attention.attention_item'));
    }

    public function test_payments_reason_codes_map_onto_the_item_types(): void
    {
        $expected = [
            'unknown_payment_request' => 'integration_inconsistency',
            'request_cancelled_in_pairrot' => 'integration_inconsistency',
            'message_rejected' => 'integration_inconsistency',
            'pairrot_review_case_opened' => 'pairrot_review',
            'inbound_address_paused' => 'integration_degraded',
        ];
        foreach (array_keys($expected) as $reason) {
            $this->publish(EventSampler::sample(IntegrationDegraded::class, ['organizerId' => $this->organizer, 'integration' => 'pairrot', 'reason' => $reason]));
        }
        $this->drainQueues();

        $rows = $this->dbRows('SELECT type, payload FROM attention.attention_item');
        self::assertCount(\count($expected), $rows);
        foreach ($rows as $row) {
            /** @var array{reason: string} $payload */
            $payload = json_decode(\is_string($row['payload']) ? $row['payload'] : '', true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($expected[$payload['reason']], $row['type'], $payload['reason']);
        }
    }

    public function test_a_platform_wide_integration_outage_has_no_organizer(): void
    {
        $this->publish(EventSampler::sample(IntegrationDegraded::class, ['organizerId' => null, 'integration' => 'pairrot']));
        $this->drainQueues();

        self::assertNull($this->dbRow("SELECT organizer_id FROM attention.attention_item WHERE type = 'integration_degraded'")['organizer_id']);
    }

    /** Only this module's transport: events that createOwner() queued for other modules are not under test. */
    protected function drainQueues(): DrainResult
    {
        return $this->queues()->drain(['attention_events']);
    }

    private function publish(object $event): void
    {
        $bus = static::getContainer()->get('event.bus');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch($event, [TenantStamp::of($this->organizer)]);
    }
}
