<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * Current state of confirmations: the latest row per (requirement, person, participation or order).
 * Implementations read the activity and variant of a participation through Registration's `ParticipationQuery`.
 */
interface ConsentQuery
{
    /**
     * Requirements that apply to the participations and are not granted for the current document version.
     *
     * @param list<ParticipationId> $participationIds
     *
     * @return array<string, list<MissingConfirmation>> keyed by participation id (string); complete participations are absent
     */
    public function missingFor(OrganizerId $organizer, array $participationIds): array;

    /**
     * Narrows persons to those whose latest decision on a consent of this type and purpose is `granted`
     * (marketing audience, PRV-07).
     *
     * @param list<PersonId> $persons
     *
     * @return list<PersonId>
     */
    public function grantedAmong(OrganizerId $organizer, RequirementType $type, string $purpose, array $persons): array;

    /**
     * Persons of the event with a granted consent of the type (for the gallery check, POST-06).
     *
     * @return list<PersonId>
     */
    public function personsWithGrantedConsent(OrganizerId $organizer, EventId $event, RequirementType $type): array;
}
