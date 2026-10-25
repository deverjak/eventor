<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Per-organizer rules that voters and payment rules read (chapter 03, `organizer_policy`).
 * `defaultDueDays` / `defaultHoldTtlHours` are the defaults for new events.
 */
final readonly class OrganizerPolicyView
{
    public function __construct(
        public OrganizerId $organizerId,
        public bool $checkInMayRecordCash,
        public bool $eventManagerMayResolvePayments,
        public bool $autoRestoreLatePayment,
        public OverpaymentHandling $overpaymentHandling,
        public int $defaultDueDays,
        public int $defaultHoldTtlHours,
    ) {}
}
