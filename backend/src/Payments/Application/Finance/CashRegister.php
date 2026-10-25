<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Finance;

use Eventor\Payments\Contract\CashReceipt;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Ledger\LedgerSource;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;

/**
 * Takes cash for an order: one idempotent ledger entry plus the recomputed state. Shared by the `CashDesk` facade
 * (check-in) and the admin command `RecordCash`; both run inside a transaction.
 */
final readonly class CashRegister
{
    public function __construct(
        private LedgerBook $book,
        private LedgerEntryRepository $ledger,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    public function record(OrganizerId $organizer, OrderId $order, Money $amount, ?CheckInStationId $station, string $idempotencyKey, ?string $note): CashReceipt
    {
        $finance = $this->book->lock($organizer, $order);

        $earlier = $this->ledger->findBySourceRef(LedgerSource::Cash, $idempotencyKey);
        if (null !== $earlier) {
            if (!$earlier->orderId()->equals($order) || !$earlier->amount()->equals($amount)) {
                throw IdempotencyKeyReused::create();
            }

            return new CashReceipt($earlier->id(), $finance->view(), true);
        }

        $entry = LedgerEntry::cash(
            $organizer,
            $order,
            $amount,
            $station,
            $idempotencyKey,
            $this->audit->actor() ?? AuditActor::system('payments'),
            $note,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->book->append($finance, [$entry]);

        return new CashReceipt($entry->id(), $finance->view(), false);
    }
}
