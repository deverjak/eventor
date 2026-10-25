<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Which legal documents and consents apply to a registration, and whether the submitted decisions satisfy them
 * (chapter 04, path 2, steps 4 and 7; ADR 012). Pure reads; the same resolver feeds the public form, the server-side
 * check in `PlaceOrder` and the admin preview.
 */
interface RequirementResolver
{
    /**
     * Merges organizer -> event -> activity documents (a lower level may replace a higher one) for the selected slots.
     * One slot is one participation-to-be.
     *
     * @param list<RegistrationSlot> $slots
     */
    public function forRegistration(OrganizerId $organizer, EventId $event, array $slots): ApplicableRequirements;

    /**
     * @param list<SubmittedDecision> $decisions
     *
     * @throws RequirementNotSatisfied when a mandatory requirement has no granted decision for the current version
     */
    public function assertSatisfied(ApplicableRequirements $requirements, array $decisions): void;
}
