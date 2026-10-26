<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Content\Application\Query\View\CollectionAdminView;
use Eventor\Content\Application\Query\View\MaterialAdminView;
use Eventor\Content\Application\Query\View\PhotoAdminView;
use Eventor\Content\Contract\Exception\CollectionNotFound;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The organizer's view of the content of one event: drafts included (POST-01..04).
 */
final readonly class AdminContentQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<CollectionAdminView>
     */
    public function collections(OrganizerId $organizer, EventId $event): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.title, c.visibility, c.external_url, c.link_token, c.published_at IS NOT NULL AS published, c.in_archive,
                    (SELECT count(*) FROM content.photo p WHERE p.collection_id = c.id) AS photo_count
               FROM content.collection c WHERE c.organizer_id = :organizer AND c.event_id = :event ORDER BY c.created_at, c.id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map(static fn(array $row): CollectionAdminView => new CollectionAdminView(
            Row::string($row, 'id'),
            Row::string($row, 'title'),
            Row::string($row, 'visibility'),
            Row::nullableString($row, 'external_url'),
            Row::nullableString($row, 'link_token'),
            Row::bool($row, 'published'),
            Row::bool($row, 'in_archive'),
            Row::int($row, 'photo_count'),
        ), $rows);
    }

    /**
     * @return list<PhotoAdminView>
     *
     * @throws CollectionNotFound
     */
    public function photos(OrganizerId $organizer, EventId $event, CollectionId $collection): array
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM content.collection WHERE id = :id AND organizer_id = :organizer AND event_id = :event',
            ['id' => $collection->toString(), 'organizer' => $organizer->toString(), 'event' => $event->toString()],
        );
        if (false === $exists) {
            throw CollectionNotFound::forId($collection);
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, description, author, event_part, position, thumbnail_key IS NOT NULL AS has_thumbnail
               FROM content.photo WHERE collection_id = :id AND organizer_id = :organizer ORDER BY position, id',
            ['id' => $collection->toString(), 'organizer' => $organizer->toString()],
        );

        return array_map(static fn(array $row): PhotoAdminView => new PhotoAdminView(
            Row::string($row, 'id'),
            Row::nullableString($row, 'description'),
            Row::nullableString($row, 'author'),
            Row::nullableString($row, 'event_part'),
            Row::int($row, 'position'),
            Row::bool($row, 'has_thumbnail'),
        ), $rows);
    }

    /**
     * @return list<MaterialAdminView>
     */
    public function materials(OrganizerId $organizer, EventId $event): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, url, type, position, published_at IS NOT NULL AS published, in_archive
               FROM content.material WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at, id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map(static fn(array $row): MaterialAdminView => new MaterialAdminView(
            Row::string($row, 'id'),
            Row::string($row, 'title'),
            Row::string($row, 'url'),
            Row::string($row, 'type'),
            Row::int($row, 'position'),
            Row::bool($row, 'published'),
            Row::bool($row, 'in_archive'),
        ), $rows);
    }
}
