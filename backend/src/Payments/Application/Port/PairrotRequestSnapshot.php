<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

/**
 * Current state of a request in Pairrot (reconcile): `paymentChangeSequence` is the latest per-request sequence.
 */
final readonly class PairrotRequestSnapshot
{
    public function __construct(
        public string $pairrotId,
        public string $externalId,
        public string $status,
        public int $paidAmountMinor,
        public int $paymentChangeSequence,
    ) {}
}
