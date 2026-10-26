<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

/**
 * The cash option of an unpaid participant (CHK-08). `available` is false when the caller has no right to take cash.
 */
final readonly class DeskCashOfferView
{
    public function __construct(
        public bool $available,
        public int $outstandingMinor,
    ) {}
}
