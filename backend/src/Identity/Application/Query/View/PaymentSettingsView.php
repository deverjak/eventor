<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * Response of `GET /api/admin/org/{orgSlug}/payment-settings`.
 */
final readonly class PaymentSettingsView
{
    public function __construct(
        public int $defaultDueDays,
        public int $defaultHoldTtlHours,
        public bool $autoRestoreLatePayment,
        public string $overpaymentHandling,
        public bool $checkInMayRecordCash,
        public bool $eventManagerMayResolvePayments,
        public bool $pairrotConnected,
        public ?string $maskedIban,
        public bool $transferEnabled,
        public bool $autoMatching,
    ) {}
}
