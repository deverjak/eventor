<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The Pairrot calls of the onboarding wizard (ORG-02, chapter 07). Identity owns the wizard and the settings, Payments
 * owns the Pairrot port; every call is idempotent so the wizard can be interrupted and repeated. Pairrot never
 * returns a key per subtenant; the management key stays in Payments.
 */
interface PairrotOnboarding
{
    /**
     * Creates the subtenant `eventor_org_<uuid>` or returns the existing one.
     *
     * @return string the Pairrot subtenant id
     *
     * @throws PairrotUnavailable
     */
    public function activateSubtenant(OrganizerId $organizer, string $organizerName, string $contactEmail): string;

    /**
     * Connects the bank account and creates the inbound address for the forwarded notifications.
     *
     * @throws BankAccountUnavailable when the account is connected elsewhere
     * @throws PairrotUnavailable
     */
    public function connectBankAccount(OrganizerId $organizer, string $subtenantId, string $accountNumberOrIban, string $label): BankConnectionView;

    /**
     * @throws PairrotUnavailable
     */
    public function receptionState(OrganizerId $organizer, string $subtenantId, string $bankConnectionId): ReceptionState;
}
