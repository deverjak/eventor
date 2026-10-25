<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * The open refund task of an order, embedded in the finance detail.
 */
final readonly class OpenRefundView
{
    public function __construct(
        public string $id,
        public MoneyView $amount,
        public string $reason,
    ) {}
}
