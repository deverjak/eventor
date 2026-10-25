<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Application\Query\View\NewsPostAdminView;
use Eventor\Catalog\Application\Query\View\ProgramItemAdminView;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read models of the program and news screens (PRG-01..04): everything the organizer wrote, drafts included.
 */
final readonly class AdminProgramQuery
{
    public function __construct(
        private Connection $connection,
        private AdminEventQuery $events,
    ) {}

    /**
     * @return list<ProgramItemAdminView>
     */
    public function program(OrganizerId $organizer, EventId $event): array
    {
        $this->assertEvent($organizer, $event);

        return array_map(static fn(array $row): ProgramItemAdminView => new ProgramItemAdminView(
            Hydrate::string($row, 'id'),
            Hydrate::nullableString($row, 'activity_id'),
            Hydrate::string($row, 'title'),
            Hydrate::nullableString($row, 'description'),
            Hydrate::nullableString($row, 'location'),
            Hydrate::nullableString($row, 'responsible'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::nullableTime($row, 'ends_at'),
            Hydrate::string($row, 'visibility'),
        ), $this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.program_item WHERE organizer_id = :organizer AND event_id = :event ORDER BY starts_at, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ));
    }

    /**
     * @return list<NewsPostAdminView>
     */
    public function news(OrganizerId $organizer, EventId $event): array
    {
        $this->assertEvent($organizer, $event);

        return array_map(static fn(array $row): NewsPostAdminView => new NewsPostAdminView(
            Hydrate::string($row, 'id'),
            Hydrate::string($row, 'title'),
            Hydrate::string($row, 'body'),
            Hydrate::int($row, 'priority'),
            Hydrate::bool($row, 'broadcast'),
            Hydrate::nullableString($row, 'author_account_id'),
            null !== $row['published_at'],
            Hydrate::nullableTime($row, 'published_at'),
            Hydrate::time($row, 'created_at'),
        ), $this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.news_post WHERE organizer_id = :organizer AND event_id = :event ORDER BY priority DESC, created_at DESC',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ));
    }

    private function assertEvent(OrganizerId $organizer, EventId $event): void
    {
        if (!$this->events->exists($organizer, $event)) {
            throw EventNotFound::forId($event);
        }
    }
}
