<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\EventPublisher;

use Eventor\Payments\Contract\Event\CashRecorded;
use Eventor\Payments\Contract\Event\LedgerEntryCorrected;
use Eventor\Payments\Contract\Event\OrderPaymentChanged;
use Eventor\Payments\Contract\Event\OverpaymentDetected;
use Eventor\Payments\Contract\Event\PaymentDisputeOpened;
use Eventor\Payments\Contract\Event\PaymentRequestFailed;
use Eventor\Payments\Contract\Event\PaymentRequestIssued;
use Eventor\Payments\Contract\Event\PaymentRevoked;
use Eventor\Payments\Contract\Event\RefundTaskOpened as RefundTaskOpenedEvent;
use Eventor\Payments\Contract\Event\RefundTaskSettled as RefundTaskSettledEvent;
use Eventor\Payments\Contract\Event\TransactionAllocated;
use Eventor\Payments\Contract\Event\UnmatchedTransactionReceived;
use Eventor\Payments\Domain\Event\LedgerEntryAppended;
use Eventor\Payments\Domain\Event\OrderFinanceRecomputed;
use Eventor\Payments\Domain\Event\PaymentRequestCreated;
use Eventor\Payments\Domain\Event\PaymentRequestCreationFailed;
use Eventor\Payments\Domain\Event\PaymentRequestMirrored;
use Eventor\Payments\Domain\Event\RefundTaskOpened;
use Eventor\Payments\Domain\Event\RefundTaskSettled;
use Eventor\Payments\Domain\Event\UnmatchedTransactionClosed;
use Eventor\Payments\Domain\Event\UnmatchedTransactionRecorded;
use Eventor\Payments\Domain\Ledger\LedgerEntryKind;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the internal domain events of Payments into the public integration events. Everything runs
 * synchronously inside the command transaction, so the INSERT into the outbox commits (or rolls back) together with
 * the ledger (ADR 003). Integration events carry ids, amounts and states only.
 */
final readonly class PublishPaymentsEvents
{
    public function __construct(
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function orderFinanceRecomputed(OrderFinanceRecomputed $event): void
    {
        $this->publish($event->organizerId, new OrderPaymentChanged(
            $event->organizerId,
            $event->orderId,
            $event->previousState,
            $event->newState,
            $event->delta,
            $event->received,
            $event->amountDue,
            $event->ledgerVersion,
            $event->occurredAt,
        ));

        // A growing excess is news; the state stays `overpaid` until a refund task is opened (then `refund_pending`).
        if (FinancialState::Overpaid === $event->newState && $event->overpaid->isPositive() && !$event->overpaid->equals($event->previousOverpaid)) {
            $this->publish($event->organizerId, new OverpaymentDetected($event->organizerId, $event->orderId, $event->overpaid, $event->occurredAt));
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function ledgerEntryAppended(LedgerEntryAppended $event): void
    {
        if (LedgerEntryKind::Cash === $event->kind) {
            $this->publish($event->organizerId, new CashRecorded($event->organizerId, $event->orderId, $event->entryId, $event->amount, $event->stationId, $event->occurredAt));
        }
        if (null !== $event->compensatesId && \in_array($event->kind, [LedgerEntryKind::CashCorrection, LedgerEntryKind::ManualCorrection], true)) {
            $this->publish($event->organizerId, new LedgerEntryCorrected($event->organizerId, $event->orderId, $event->entryId, $event->compensatesId, $event->amount, $event->occurredAt));
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function paymentRequestCreated(PaymentRequestCreated $event): void
    {
        $this->publish($event->organizerId, new PaymentRequestIssued(
            $event->organizerId,
            $event->orderId,
            $event->paymentRequestId,
            $event->variableSymbol,
            $event->amount,
            $event->dueAt,
            $event->purpose,
            $event->occurredAt,
        ));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function paymentRequestCreationFailed(PaymentRequestCreationFailed $event): void
    {
        $this->publish($event->organizerId, new PaymentRequestFailed(
            $event->organizerId,
            $event->orderId,
            $event->paymentRequestId,
            $event->attempts,
            $event->lastError,
            $event->occurredAt,
        ));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function paymentRequestMirrored(PaymentRequestMirrored $event): void
    {
        if ('allocation_revoked' === $event->cause && $event->delta->isNegative()) {
            $this->publish($event->organizerId, new PaymentRevoked($event->organizerId, $event->orderId, $event->paymentRequestId, $event->delta->negate(), $event->occurredAt));
        }
        if ('transaction_review_required' === $event->cause) {
            $this->publish($event->organizerId, new PaymentDisputeOpened($event->organizerId, $event->orderId, $event->paymentRequestId, 'event:'.$event->pairrotEventId, $event->occurredAt));
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function refundTaskOpened(RefundTaskOpened $event): void
    {
        $this->publish($event->organizerId, new RefundTaskOpenedEvent($event->organizerId, $event->refundTaskId, $event->orderId, $event->amount, $event->reason, $event->occurredAt));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function refundTaskSettled(RefundTaskSettled $event): void
    {
        $this->publish($event->organizerId, new RefundTaskSettledEvent($event->organizerId, $event->refundTaskId, $event->orderId, $event->amount, $event->occurredAt));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function unmatchedTransactionRecorded(UnmatchedTransactionRecorded $event): void
    {
        $this->publish($event->organizerId, new UnmatchedTransactionReceived(
            $event->organizerId,
            $event->transactionId,
            $event->amount,
            $event->variableSymbol,
            $event->matchingResult,
            $event->bookedOn,
            $event->occurredAt,
        ));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function unmatchedTransactionClosed(UnmatchedTransactionClosed $event): void
    {
        if ('allocated' === $event->resolution && null !== $event->paymentRequestId && null !== $event->orderId) {
            $this->publish($event->organizerId, new TransactionAllocated($event->organizerId, $event->transactionId, $event->paymentRequestId, $event->orderId, $event->occurredAt));
        }
    }

    private function publish(OrganizerId $organizer, object $event): void
    {
        $this->eventBus->dispatch($event, [TenantStamp::of($organizer)]);
    }
}
