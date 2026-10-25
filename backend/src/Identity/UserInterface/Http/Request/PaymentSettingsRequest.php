<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Eventor\Identity\Contract\OverpaymentHandling;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `PUT /api/admin/org/{orgSlug}/payment-settings` (ORG-02): payment defaults and policy exceptions.
 */
final readonly class PaymentSettingsRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 365)]
        public int $defaultDueDays = 14,
        #[Assert\Range(min: 1, max: 720)]
        public int $defaultHoldTtlHours = 48,
        public bool $autoRestoreLatePayment = false,
        public OverpaymentHandling $overpaymentHandling = OverpaymentHandling::Ask,
        public bool $checkInMayRecordCash = false,
        public bool $eventManagerMayResolvePayments = false,
    ) {}
}
