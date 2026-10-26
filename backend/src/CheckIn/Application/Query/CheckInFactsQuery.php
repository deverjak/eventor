<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\CheckIn\Application\Presenter\Cells;
use Eventor\CheckIn\Contract\CheckInCounts;
use Eventor\CheckIn\Contract\CheckInListView;
use Eventor\CheckIn\Contract\CheckInQuery;
use Eventor\CheckIn\Contract\CheckInRecordView;
use Eventor\CheckIn\Contract\Exception\CheckInListNotFound;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The `CheckInQuery` facade of the Contract (CHK-10) for the modules that need to know who arrived.
 */
#[AsAlias(CheckInQuery::class)]
final readonly class CheckInFactsQuery implements CheckInQuery
{
    public function __construct(
        private Connection $connection,
        private ExpectedParticipantsQuery $expected,
    ) {}

    public function lists(OrganizerId $organizer, EventId $event): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, event_id, activity_id, name, opens_at FROM checkin.check_in_list
              WHERE organizer_id = :organizer AND event_id = :event AND cancelled_at IS NULL
              ORDER BY opens_at NULLS LAST, name, id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map(static fn(array $row): CheckInListView => new CheckInListView(
            CheckInListId::fromString(Cells::string($row['id'])),
            EventId::fromString(Cells::string($row['event_id'])),
            ActivityId::fromString(Cells::string($row['activity_id'])),
            Cells::string($row['name']),
            Cells::nullableTime($row['opens_at']),
        ), $rows);
    }

    public function recordFor(OrganizerId $organizer, ParticipationId $participation): ?CheckInRecordView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, participation_id, list_id, station_id, checked_in_at, checked_in_by FROM checkin.check_in
              WHERE organizer_id = :organizer AND participation_id = :participation AND voided_at IS NULL',
            ['organizer' => $organizer->toString(), 'participation' => $participation->toString()],
        );
        if (false === $row) {
            return null;
        }
        $station = Cells::nullableString($row['station_id']);

        return new CheckInRecordView(
            CheckInId::fromString(Cells::string($row['id'])),
            ParticipationId::fromString(Cells::string($row['participation_id'])),
            CheckInListId::fromString(Cells::string($row['list_id'])),
            null === $station ? null : CheckInStationId::fromString($station),
            Cells::time($row['checked_in_at']),
            Cells::nullableString($row['checked_in_by']),
        );
    }

    public function checkedIn(OrganizerId $organizer, array $participations): array
    {
        if ([] === $participations) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT participation_id FROM checkin.check_in WHERE organizer_id = :organizer AND voided_at IS NULL AND participation_id IN (:ids)',
            ['organizer' => $organizer->toString(), 'ids' => array_map(static fn(ParticipationId $id): string => $id->toString(), $participations)],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_map(static fn(string $id): ParticipationId => ParticipationId::fromString($id), $ids);
    }

    public function counts(OrganizerId $organizer, CheckInListId $list): CheckInCounts
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT event_id, activity_id FROM checkin.check_in_list WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $list->toString()],
        );
        if (false === $row) {
            throw CheckInListNotFound::forId($list);
        }
        $activity = Cells::string($row['activity_id']);
        $members = $this->expected->byActivity($organizer, EventId::fromString(Cells::string($row['event_id'])), [ParticipationState::Confirmed])[$activity] ?? [];

        return new CheckInCounts($list, ExpectedParticipantsQuery::count($members), $this->validCount($organizer, $list));
    }

    private function validCount(OrganizerId $organizer, CheckInListId $list): int
    {
        return Cells::int($this->connection->fetchOne(
            'SELECT count(*) FROM checkin.check_in WHERE organizer_id = :organizer AND list_id = :list AND voided_at IS NULL',
            ['organizer' => $organizer->toString(), 'list' => $list->toString()],
        ));
    }
}
