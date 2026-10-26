<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query;

use Eventor\Registration\Contract\AudienceCriteria;
use Eventor\Registration\Contract\AudienceMember;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\RecipientQuery;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Who is expected at the doors of an event, by activity (CHK-10): the participations of Registration in the given
 * states, grouped the way Registration groups an audience (order and activity). This live Registration facade is
 * authoritative, so CheckIn intentionally keeps no local projection of participation cancellation, confirmation, or
 * variant-change events.
 */
final readonly class ExpectedParticipantsQuery
{
    public function __construct(private RecipientQuery $recipients) {}

    /**
     * @param list<ParticipationState> $states
     *
     * @return array<string, list<AudienceMember>> by activity id
     */
    public function byActivity(OrganizerId $organizer, EventId $event, array $states): array
    {
        $byActivity = [];
        foreach ($this->recipients->resolveAudience($organizer, new AudienceCriteria($event, [], [], $states)) as $member) {
            $byActivity[$member->activityId->toString()][] = $member;
        }

        return $byActivity;
    }

    /**
     * @param list<AudienceMember> $members
     */
    public static function count(array $members): int
    {
        return array_sum(array_map(static fn(AudienceMember $member): int => \count($member->participationIds), $members));
    }
}
