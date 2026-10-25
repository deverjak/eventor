<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * What Payments needs to know about the organizer policy (Identity owns it; the adapter reads `OrganizerSettings`).
 */
interface OrganizerPaymentPolicy
{
    public function checkInMayRecordCash(OrganizerId $organizer): bool;

    public function overpaymentHandling(OrganizerId $organizer): OverpaymentHandling;

    public function defaultDueDays(OrganizerId $organizer): int;
}
