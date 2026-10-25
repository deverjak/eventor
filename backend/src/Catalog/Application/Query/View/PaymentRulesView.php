<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * Payment and cancellation rules of an event (EVT-10) as the admin sees them.
 */
final readonly class PaymentRulesView
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public array $methods,
        public int $dueDays,
        public int $holdTtlHours,
        public bool $autoRestoreOnLatePayment,
        public bool $selfServiceCancellationAllowed,
        public ?string $selfServiceCancellationDeadline,
        public string $currency,
    ) {}
}
