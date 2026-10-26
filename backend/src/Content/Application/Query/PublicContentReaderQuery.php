<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Content\Application\Query\View\EventContentView;
use Eventor\Content\Application\Query\View\PhotoFileView;
use Eventor\Content\Contract\CollectionView;
use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Content\Contract\Exception\CollectionNotFound;
use Eventor\Content\Contract\MaterialView;
use Eventor\Content\Contract\PhotoView;
use Eventor\Content\Contract\PublicContentReader;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PhotoId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Published content as visitors see it (PUB-06, POST-03, POST-07). Only published items, only the visibility asked for;
 * nothing personal: a photo carries its author's name only as the organizer typed it as a credit.
 */
#[AsAlias(PublicContentReader::class)]
final readonly class PublicContentReaderQuery implements PublicContentReader
{
    public const string PHOTO_URL = '/api/public/content/photos/';

    public function __construct(private Connection $connection) {}

    public function collectionsFor(OrganizerId $organizer, EventId $event, CollectionVisibility $audience): array
    {
        return $this->collectionRows($organizer, $event, 'c.visibility = :visibility', ['visibility' => $audience->value]);
    }

    public function photos(OrganizerId $organizer, CollectionId $collection, CollectionVisibility $audience, int $limit = 60, int $offset = 0): array
    {
        $token = $this->connection->fetchOne(
            'SELECT coalesce(link_token, \'\') FROM content.collection WHERE id = :id AND organizer_id = :organizer AND published_at IS NOT NULL AND visibility = :visibility',
            ['id' => $collection->toString(), 'organizer' => $organizer->toString(), 'visibility' => $audience->value],
        );
        if (!\is_string($token)) {
            throw CollectionNotFound::forId($collection);
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, author, position FROM content.photo WHERE collection_id = :id ORDER BY position, id LIMIT :limit OFFSET :offset',
            ['id' => $collection->toString(), 'limit' => max(1, min(100, $limit)), 'offset' => max(0, $offset)],
        );
        $query = '' === $token ? '' : '?token='.$token;

        return array_map(static fn(array $row): PhotoView => new PhotoView(
            PhotoId::fromString(Row::string($row, 'id')),
            self::PHOTO_URL.Row::string($row, 'id').$query,
            self::PHOTO_URL.Row::string($row, 'id').'/thumbnail'.$query,
            Row::nullableString($row, 'author'),
            Row::int($row, 'position'),
        ), $rows);
    }

    public function materials(OrganizerId $organizer, EventId $event): array
    {
        return $this->materialRows($organizer, $event, 'true');
    }

    /**
     * The public page of an event (PUB-06): its published public collections and materials.
     */
    public function page(OrganizerId $organizer, EventId $event): EventContentView
    {
        return new EventContentView($this->collectionsFor($organizer, $event, CollectionVisibility::Public), $this->materials($organizer, $event));
    }

    /**
     * The public archive of a finished event (POST-07): only what the organizer designated, and only public collections.
     */
    public function archive(OrganizerId $organizer, EventId $event): EventContentView
    {
        return new EventContentView(
            $this->collectionRows($organizer, $event, 'c.visibility = :visibility AND c.in_archive', ['visibility' => CollectionVisibility::Public->value]),
            $this->materialRows($organizer, $event, 'in_archive'),
        );
    }

    /**
     * The collection behind an unguessable link (POST-03), if it is published.
     */
    public function linkOnly(OrganizerId $organizer, EventId $event, string $token): ?CollectionId
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM content.collection WHERE organizer_id = :organizer AND event_id = :event AND link_token = :token AND visibility = :visibility AND published_at IS NOT NULL',
            ['organizer' => $organizer->toString(), 'event' => $event->toString(), 'token' => $token, 'visibility' => CollectionVisibility::LinkOnly->value],
        );

        return \is_string($id) ? CollectionId::fromString($id) : null;
    }

    /**
     * The stored file of a photo visible without login: in a published public collection, or in a published link-only
     * one when the right token comes along.
     */
    public function photoFile(PhotoId $photo, ?string $token): ?PhotoFileView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.storage_key, p.thumbnail_key, p.content_type FROM content.photo p JOIN content.collection c ON c.id = p.collection_id
              WHERE p.id = :id AND c.published_at IS NOT NULL AND (c.visibility = :public OR (c.visibility = :link AND c.link_token = :token))',
            ['id' => $photo->toString(), 'public' => CollectionVisibility::Public->value, 'link' => CollectionVisibility::LinkOnly->value, 'token' => $token ?? ''],
        );

        return false === $row ? null : new PhotoFileView(Row::string($row, 'storage_key'), Row::nullableString($row, 'thumbnail_key'), Row::string($row, 'content_type'));
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<CollectionView>
     */
    private function collectionRows(OrganizerId $organizer, EventId $event, string $condition, array $parameters): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.event_id, c.title, c.visibility, c.external_url,
                    (SELECT count(*) FROM content.photo p WHERE p.collection_id = c.id) AS photo_count,
                    (SELECT p.id FROM content.photo p WHERE p.collection_id = c.id AND p.thumbnail_key IS NOT NULL ORDER BY p.position, p.id LIMIT 1) AS cover_id
               FROM content.collection c
              WHERE c.organizer_id = :organizer AND c.event_id = :event AND c.published_at IS NOT NULL AND '.$condition.'
              ORDER BY c.created_at, c.id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()] + $parameters,
        );

        return array_map(static function (array $row): CollectionView {
            $cover = Row::nullableString($row, 'cover_id');

            return new CollectionView(
                CollectionId::fromString(Row::string($row, 'id')),
                EventId::fromString(Row::string($row, 'event_id')),
                Row::string($row, 'title'),
                CollectionVisibility::from(Row::string($row, 'visibility')),
                Row::nullableString($row, 'external_url'),
                Row::int($row, 'photo_count'),
                null === $cover ? null : self::PHOTO_URL.$cover.'/thumbnail',
            );
        }, $rows);
    }

    /**
     * @return list<MaterialView>
     */
    private function materialRows(OrganizerId $organizer, EventId $event, string $condition): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT title, url, type FROM content.material
              WHERE organizer_id = :organizer AND event_id = :event AND published_at IS NOT NULL AND '.$condition.'
              ORDER BY position, created_at, id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map(static fn(array $row): MaterialView => new MaterialView(Row::string($row, 'title'), Row::string($row, 'url'), Row::string($row, 'type')), $rows);
    }
}
