<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Payments\Application\Finance\LedgerBook;
use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Contract\PaymentRequestStatus;
use Eventor\Payments\Domain\Inbox\InboxMessageRepository;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * ApplyPairrotPaymentUpdate (ADR 004, chapter 07):
 *
 *     ignore the event unless data.sequence > payment_request.last_sequence      (S03, out of order)
 *     delta = current.paid_amount - payment_request.known_paid_amount
 *     delta > 0  -> ledger += pairrot_allocation(+delta)    delta < 0 -> ledger += pairrot_revocation(delta)
 *     mirror the request; fold the ledger against the amount due; publish order_payment_changed
 *
 * Works with the STATE, not the single payment, so gaps in `sequence` and lost webhooks do not matter: the next event
 * carries the total and the delta catches up. The Pairrot status is only mirrored; the fold of OUR ledger against OUR
 * amount due decides the financial state (cash and manual corrections are invisible to Pairrot).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyPairrotPaymentUpdateHandler
{
    public function __construct(
        private InboxMessageRepository $inbox,
        private PaymentRequestRepository $requests,
        private LedgerBook $book,
        private LedgerEntryRepository $ledger,
        private GatewayAlerts $alerts,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ApplyPairrotPaymentUpdate $command): void
    {
        $organizer = $this->tenant->require();
        $message = $this->inbox->lock($command->inboxMessageId);
        if (null === $message) {
            return;
        }
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        if (!$message->isPending()) {
            // Redelivery of the queue message after the first one committed: still record the (technical) fact, change nothing.
            $message->processed($organizer, $now);

            return;
        }

        $event = PairrotEvent::fromPayload($message->payload());
        $pairrotRequestId = $event->string('payment_request_id');
        $request = $this->requests->lockByPairrotReference($pairrotRequestId, $event->optionalString('external_id'));
        if (null === $request || !$request->organizerId()->equals($organizer)) {
            $this->alerts->anomaly($organizer, 'unknown_payment_request:'.$pairrotRequestId);
            $message->rejected($organizer, 'unknown_payment_request', $now);

            return;
        }

        $current = $event->object('current');
        $status = PaymentRequestStatus::tryFrom(\is_string($current['status'] ?? null) ? $current['status'] : '') ?? PaymentRequestStatus::Open;
        $paid = \is_int($current['paid_amount'] ?? null) ? $current['paid_amount'] : 0;
        $cause = $event->optionalString('cause') ?? 'snapshot';

        if (PaymentRequestStatus::Cancelled === $status && null === $request->cancelledAt()) {
            // Not cancelled by Eventor: somebody did it in Pairrot. Surface it; the mirror still follows.
            $this->alerts->anomaly($organizer, 'request_cancelled_in_pairrot:'.$request->id()->toString());
        }

        $delta = $request->mirror($event->int('sequence'), $status, $paid, $cause, $event->id, $event->createdAt);
        if (null === $delta) {
            $message->processed($organizer, $now); // not newer than what we applied

            return;
        }

        $finance = $this->book->lock($organizer, $request->orderId());
        $entries = [];
        if (!$delta->isZero()) {
            $entries[] = LedgerEntry::pairrotDelta(
                $organizer,
                $request->orderId(),
                $request->id(),
                Money::of($delta->minor, Currency::CZK),
                $event->id,
                $delta->isNegative() ? $this->ledger->latestAllocationOfRequest($request->id())?->id() : null,
                $event->createdAt,
                $now,
            );
        }

        $disputed = $finance->isDisputed();
        if ('transaction_review_required' === $cause) {
            $finance->markDisputed(true);
        } elseif (\in_array($cause, ['allocation_created', 'allocation_revoked', 'request_cancelled'], true)) {
            $finance->markDisputed(false);
        }

        if ([] !== $entries) {
            $this->book->append($finance, $entries);
        } elseif ($disputed !== $finance->isDisputed()) {
            $this->book->refresh($finance);
        }

        $message->processed($organizer, $now);
    }
}
