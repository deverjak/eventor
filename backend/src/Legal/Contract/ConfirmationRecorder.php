<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Legal\Contract\Exception\ConfirmationNotAllowed;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Appends decisions to the confirmation ledger (LEG-07). A writing facade: it runs in the transaction of the caller,
 * so only a handler already on `command.bus` may call it (Registration in `PlaceOrder` and in the self-service
 * commands, CheckIn for a confirmation on site). Emits `legal.confirmation_recorded` (and `legal.consent_withdrawn`).
 */
interface ConfirmationRecorder
{
    /**
     * @param list<ConfirmationToRecord> $confirmations
     *
     * @return list<ConfirmationId> in the order of the input
     *
     * @throws ConfirmationNotAllowed e.g. declining a mandatory requirement, withdrawing a non-revocable one, a staff decision without a note
     */
    public function record(OrganizerId $organizer, array $confirmations): array;
}
