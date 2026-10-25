<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Application\Query\View\PublishedFormVersionView;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Published form versions for other modules and the public zone. DBAL with an explicit organizer: callers have no
 * tenant (public requests, subscribers). A draft is never visible here.
 */
readonly class PublishedFormQuery
{
    private const string COLUMNS = 'id, event_id, number, published_at, schema_sha256, schema';

    public function __construct(private Connection $connection) {}

    /**
     * @throws FormVersionNotFound
     */
    public function version(OrganizerId $organizer, FormVersionId $version): PublishedFormVersionView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM catalog.form_version WHERE organizer_id = :organizer AND id = :id AND published_at IS NOT NULL',
            ['organizer' => $organizer->toString(), 'id' => $version->toString()],
        );
        if (false === $row) {
            throw FormVersionNotFound::forId($version);
        }

        return $this->hydrate($row);
    }

    /**
     * The version that new registrations are validated against.
     *
     * @throws EventNotFound when the event has no published form (it is a draft or unknown)
     */
    public function current(OrganizerId $organizer, EventId $event): PublishedFormVersionView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM catalog.form_version
              WHERE organizer_id = :organizer AND event_id = :event AND published_at IS NOT NULL
              ORDER BY number DESC LIMIT 1',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );
        if (false === $row) {
            throw EventNotFound::forId($event);
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PublishedFormVersionView
    {
        return new PublishedFormVersionView(
            FormVersionId::fromString(Hydrate::string($row, 'id')),
            EventId::fromString(Hydrate::string($row, 'event_id')),
            Hydrate::int($row, 'number'),
            Hydrate::time($row, 'published_at'),
            Hydrate::string($row, 'schema_sha256'),
            FormSchema::fromArray(Hydrate::json($row, 'schema')),
        );
    }
}
