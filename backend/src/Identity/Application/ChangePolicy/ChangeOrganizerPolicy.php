<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\ChangePolicy;

use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Payment defaults and policy exceptions of the organizer (ORG-02).
 */
final readonly class ChangeOrganizerPolicy
{
    public function __construct(
        public OrganizerId $organizerId,
        public int $defaultDueDays,
        public int $defaultHoldTtlHours,
        public bool $autoRestoreLatePayment,
        public OverpaymentHandling $overpaymentHandling,
        public bool $checkInMayRecordCash,
        public bool $eventManagerMayResolvePayments,
    ) {}
}
