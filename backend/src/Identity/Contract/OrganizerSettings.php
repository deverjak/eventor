<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read access to the settings of an organizer that other modules need to act: payment onboarding state (Payments,
 * Catalog publish rules), the organizer policy (Payments, CheckIn, Registration) and the sender data of messages
 * (Communication). The entity never leaves Identity.
 */
interface OrganizerSettings
{
    /**
     * @throws OrganizerNotFound
     */
    public function paymentSettings(OrganizerId $organizer): OrganizerPaymentSettings;

    /**
     * @throws OrganizerNotFound
     */
    public function policy(OrganizerId $organizer): OrganizerPolicyView;

    /**
     * @throws OrganizerNotFound
     */
    public function contact(OrganizerId $organizer): OrganizerContact;
}
