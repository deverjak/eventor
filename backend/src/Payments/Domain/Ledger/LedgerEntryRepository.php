<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;

interface LedgerEntryRepository
{
    public function add(LedgerEntry $entry): void;

    public function find(LedgerEntryId $id): ?LedgerEntry;

    /**
     * Entries already written to the database, oldest first (the caller adds the ones it has not flushed yet).
     *
     * @return list<LedgerEntry>
     */
    public function forOrder(OrderId $order): array;

    /**
     * An earlier booking with the same idempotency key (S03, cash retries), or null.
     */
    public function findBySourceRef(LedgerSource $source, string $sourceRef): ?LedgerEntry;

    /**
     * The newest Pairrot allocation of a payment request: what a revocation reverses (informational link).
     */
    public function latestAllocationOfRequest(\Eventor\Shared\Domain\Id\PaymentRequestId $request): ?LedgerEntry;

    /**
     * Has this cash entry already been compensated? (S11: once.)
     */
    public function findCorrectionOf(LedgerEntryId $original): ?LedgerEntry;
}
