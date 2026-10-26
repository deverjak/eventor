<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\CheckIn\Application\Desk\CheckInAssessor;
use Eventor\CheckIn\Application\Desk\CheckInListGate;
use Eventor\CheckIn\Application\Presenter\Cells;
use Eventor\CheckIn\Application\Query\View\CheckInListSummaryView;
use Eventor\CheckIn\Application\Query\View\DeskCashOfferView;
use Eventor\CheckIn\Application\Query\View\DeskCheckInView;
use Eventor\CheckIn\Application\Query\View\DeskHitView;
use Eventor\CheckIn\Application\Query\View\DeskMissingConfirmationView;
use Eventor\CheckIn\Application\Query\View\DeskOtherParticipationView;
use Eventor\CheckIn\Application\Query\View\DeskParticipantView;
use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInDecision;
use Eventor\Registration\Contract\MissingConfirmationView;
use Eventor\Registration\Contract\OtherParticipationView;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationSearchHit;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The read models of the check-in desk: the lists with their counters (CHK-01), the search (CHK-02) and the detail of
 * a participant with the verdict of the exception matrix (CHK-04, CHK-09).
 */
final readonly class CheckInDeskQuery
{
    public function __construct(
        private Connection $connection,
        private CheckInListGate $gate,
        private CheckInAssessor $assessor,
        private ParticipationQuery $participations,
        private ExpectedParticipantsQuery $expected,
    ) {}

    /**
     * The open lists of the event; `$only` narrows them to one (a device token bound to a list).
     *
     * @return list<CheckInListSummaryView>
     */
    public function lists(OrganizerId $organizer, EventId $event, ?CheckInListId $only = null): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.event_id, l.activity_id, l.name, l.opens_at,
                    (SELECT count(*) FROM checkin.check_in c WHERE c.list_id = l.id AND c.voided_at IS NULL) AS checked_in
               FROM checkin.check_in_list l
              WHERE l.organizer_id = :organizer AND l.event_id = :event AND l.cancelled_at IS NULL
                AND (CAST(:only AS uuid) IS NULL OR l.id = CAST(:only AS uuid))
              ORDER BY l.opens_at NULLS LAST, l.name, l.id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString(), 'only' => $only?->toString()],
        );
        $expected = [] === $rows ? [] : $this->expected->byActivity($organizer, $event, [ParticipationState::Confirmed]);

        return array_map(static fn(array $row): CheckInListSummaryView => new CheckInListSummaryView(
            CheckInListId::fromString(Cells::string($row['id'])),
            EventId::fromString(Cells::string($row['event_id'])),
            ActivityId::fromString(Cells::string($row['activity_id'])),
            Cells::string($row['name']),
            Cells::nullableTime($row['opens_at']),
            ExpectedParticipantsQuery::count($expected[Cells::string($row['activity_id'])] ?? []),
            Cells::int($row['checked_in']),
        ), $rows);
    }

    /**
     * Search by name or registration code among the participations of the list activity (CHK-02).
     *
     * @return list<DeskHitView>
     */
    public function search(OrganizerId $organizer, EventId $event, CheckInListId $list, string $term): array
    {
        $activity = $this->gate->open($event->toString(), $list->toString())->activityId();
        $hits = $this->participations->searchForCheckIn($organizer, $activity, $term);
        $arrivals = $this->arrivals($organizer, array_map(static fn(ParticipationSearchHit $hit): string => $hit->participationId->toString(), $hits));

        return array_map(static fn(ParticipationSearchHit $hit): DeskHitView => new DeskHitView(
            $hit->participationId,
            $hit->displayName,
            $hit->registrationCode,
            $hit->activityId,
            $hit->state->value,
            $arrivals[$hit->participationId->toString()] ?? null,
        ), $hits);
    }

    /**
     * The detail of one participant on one list. `$mayRecordCash`: the caller holds the cash permission, so an unpaid
     * participant is offered the one-step "cash and check in".
     */
    public function participant(OrganizerId $organizer, EventId $event, CheckInListId $list, ParticipationId $participation, bool $mayRecordCash): DeskParticipantView
    {
        $activity = $this->gate->open($event->toString(), $list->toString())->activityId();
        $assessment = $this->assessor->assess($organizer, $activity, $participation);
        $view = $assessment->participation;
        $verdict = $assessment->verdict;
        $existing = $assessment->existing;
        $cash = CheckInDecision::Warn === $verdict->decision && CheckInSituation::Unpaid === $verdict->situation
            ? new DeskCashOfferView($mayRecordCash, $view->finance->outstanding()->minor)
            : null;

        return new DeskParticipantView(
            $view->id,
            $view->displayName,
            $view->registrationCode,
            $view->activityId,
            $view->activityName,
            $view->variantName,
            $view->waveName,
            $view->categoryCode,
            $view->state->value,
            $view->finance->state->value,
            $view->finance->amountDue->minor,
            $view->finance->received->minor,
            array_map(static fn(MissingConfirmationView $missing): DeskMissingConfirmationView => new DeskMissingConfirmationView($missing->label, $missing->mandatory), $view->missingConfirmations),
            $view->checkInNote,
            array_map(static fn(OtherParticipationView $other): DeskOtherParticipationView => new DeskOtherParticipationView($other->participationId, $other->activityId, $other->activityName, $other->startsAt, $other->state->value), $view->otherParticipations),
            $verdict->decision->value,
            $verdict->situation?->value,
            null === $existing ? null : new DeskCheckInView($existing->id(), $existing->checkedInAt(), $existing->checkedInBy(), $existing->deviceLabel(), $existing->stationId()?->toString(), $existing->cashMinor()),
            $cash,
        );
    }

    /**
     * @param list<string> $participationIds
     *
     * @return array<string, \DateTimeImmutable> arrival time by participation id
     */
    private function arrivals(OrganizerId $organizer, array $participationIds): array
    {
        if ([] === $participationIds) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT participation_id, checked_in_at FROM checkin.check_in
              WHERE organizer_id = :organizer AND voided_at IS NULL AND participation_id IN (:ids)',
            ['organizer' => $organizer->toString(), 'ids' => $participationIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $arrivals = [];
        foreach ($rows as $row) {
            $arrivals[Cells::string($row['participation_id'])] = Cells::time($row['checked_in_at']);
        }

        return $arrivals;
    }
}
