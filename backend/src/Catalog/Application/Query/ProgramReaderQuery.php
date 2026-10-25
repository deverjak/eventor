<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\ProgramItemNotFound;
use Eventor\Catalog\Contract\ProgramItemView;
use Eventor\Catalog\Contract\ProgramReader;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The schedule of an event (PRG-01..04) for the public page, the participants and the change notifications. The
 * audience decides what is visible: the public sees public items, participants see both kinds.
 */
#[AsAlias(ProgramReader::class)]
final readonly class ProgramReaderQuery implements ProgramReader
{
    private const string COLUMNS = 'id, event_id, activity_id, title, description, location, starts_at, ends_at, visibility';

    public function __construct(private Connection $connection) {}

    public function forEvent(OrganizerId $organizer, EventId $event, ProgramVisibility $audience): array
    {
        $exists = $this->connection->fetchOne(
            "SELECT 1 FROM catalog.event WHERE organizer_id = :organizer AND id = :id AND publication_state <> 'draft'",
            ['organizer' => $organizer->toString(), 'id' => $event->toString()],
        );
        if (false === $exists) {
            throw EventNotFound::forId($event);
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM catalog.program_item WHERE organizer_id = :organizer AND event_id = :event'
            .(ProgramVisibility::Public === $audience ? " AND visibility = 'public'" : '')
            .' ORDER BY starts_at, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map($this->view(...), $rows);
    }

    public function item(OrganizerId $organizer, ProgramItemId $item): ProgramItemView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM catalog.program_item WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $item->toString()],
        );
        if (false === $row) {
            throw ProgramItemNotFound::forId($item);
        }

        return $this->view($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function view(array $row): ProgramItemView
    {
        $activity = Hydrate::nullableString($row, 'activity_id');

        return new ProgramItemView(
            ProgramItemId::fromString(Hydrate::string($row, 'id')),
            EventId::fromString(Hydrate::string($row, 'event_id')),
            null === $activity ? null : ActivityId::fromString($activity),
            Hydrate::string($row, 'title'),
            Hydrate::nullableString($row, 'description'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::nullableTime($row, 'ends_at'),
            Hydrate::nullableString($row, 'location'),
            ProgramVisibility::from(Hydrate::string($row, 'visibility')),
        );
    }
}
