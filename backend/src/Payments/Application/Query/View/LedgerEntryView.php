<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * One line of the append-only ledger of an order.
 */
final readonly class LedgerEntryView
{
    /**
     * @param array{type: string, id: string|null, label: string|null}|null $actor
     */
    public function __construct(
        public string $id,
        public string $kind,
        public MoneyView $amount,
        public string $source,
        public ?string $compensatesId,
        public ?string $paymentRequestId,
        public ?string $stationId,
        public ?array $actor,
        public ?string $reason,
        public ?string $occurredAt,
        public ?string $recordedAt,
    ) {}
}
