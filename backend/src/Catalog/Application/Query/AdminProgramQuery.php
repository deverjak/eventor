<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
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
     * @return list<array<string, mixed>>
     */
    public function program(OrganizerId $organizer, EventId $event): array
    {
        $this->assertEvent($organizer, $event);

        return array_map(static fn(array $row): array => [
            'id' => Hydrate::string($row, 'id'),
            'activityId' => Hydrate::nullableString($row, 'activity_id'),
            'title' => Hydrate::string($row, 'title'),
            'description' => Hydrate::nullableString($row, 'description'),
            'location' => Hydrate::nullableString($row, 'location'),
            'responsible' => Hydrate::nullableString($row, 'responsible'),
            'startsAt' => Hydrate::time($row, 'starts_at'),
            'endsAt' => Hydrate::nullableTime($row, 'ends_at'),
            'visibility' => Hydrate::string($row, 'visibility'),
        ], $this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.program_item WHERE organizer_id = :organizer AND event_id = :event ORDER BY starts_at, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function news(OrganizerId $organizer, EventId $event): array
    {
        $this->assertEvent($organizer, $event);

        return array_map(static fn(array $row): array => [
            'id' => Hydrate::string($row, 'id'),
            'title' => Hydrate::string($row, 'title'),
            'body' => Hydrate::string($row, 'body'),
            'priority' => Hydrate::int($row, 'priority'),
            'broadcast' => Hydrate::bool($row, 'broadcast'),
            'authorAccountId' => Hydrate::nullableString($row, 'author_account_id'),
            'published' => null !== $row['published_at'],
            'publishedAt' => Hydrate::nullableTime($row, 'published_at'),
            'createdAt' => Hydrate::time($row, 'created_at'),
        ], $this->connection->fetchAllAssociative(
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
