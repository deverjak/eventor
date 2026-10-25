<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Read model of participations for other modules. Every method is tenant-explicit; a participation of another
 * organizer is "not found". DTOs carry display names (personal data) only where the consumer must show them
 * (check-in desk); events never do.
 */
interface ParticipationQuery
{
    /**
     * @throws ParticipationNotFound
     */
    public function find(OrganizerId $organizer, ParticipationId $id): ParticipationView;

    /**
     * @param list<ParticipationId> $ids
     *
     * @return list<ParticipationView> unknown ids are skipped
     */
    public function findMany(OrganizerId $organizer, array $ids): array;

    /**
     * The check-in projection (CHK-04): no birth date, no contacts, no form answers.
     *
     * @throws ParticipationNotFound
     */
    public function forCheckIn(OrganizerId $organizer, ParticipationId $id): ParticipationCheckInView;

    /**
     * Resolves a registration code typed or scanned at the desk (CHK-01).
     */
    public function findByRegistrationCode(OrganizerId $organizer, string $registrationCode): ?ParticipationId;

    /**
     * Search by name or code among the participations of an activity (CHK-01). Cancelled and expired ones are included
     * so the desk can explain why they cannot check in.
     *
     * @return list<ParticipationSearchHit>
     */
    public function searchForCheckIn(OrganizerId $organizer, ActivityId $activity, string $term, int $limit = 20): array;
}
