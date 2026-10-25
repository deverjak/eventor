<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read models of the form editor (FRM): the draft, the published versions and one version in full.
 */
final readonly class AdminFormQuery
{
    public function __construct(
        private Connection $connection,
        private FormSchemaPresenter $presenter,
        private AdminEventQuery $events,
    ) {}

    /**
     * @return array{draft: array<string, mixed>|null, published: array<string, mixed>|null, versions: list<array<string, mixed>>}
     *
     * @throws EventNotFound
     */
    public function overview(OrganizerId $organizer, EventId $event): array
    {
        $this->assertEvent($organizer, $event);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, number, schema, schema_sha256, published_at, created_at, updated_at FROM catalog.form_version
              WHERE organizer_id = :organizer AND event_id = :event ORDER BY number DESC',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        $draft = null;
        $published = null;
        $versions = [];
        foreach ($rows as $row) {
            $isPublished = null !== $row['published_at'];
            if ($isPublished) {
                $versions[] = [
                    'id' => Hydrate::string($row, 'id'),
                    'number' => Hydrate::int($row, 'number'),
                    'publishedAt' => Hydrate::time($row, 'published_at'),
                    'schemaSha256' => Hydrate::nullableString($row, 'schema_sha256'),
                ];
            }
            if (!$isPublished && null === $draft) {
                $draft = $this->view($row);
            }
            if ($isPublished && null === $published) {
                $published = $this->view($row);
            }
        }

        return ['draft' => $draft, 'published' => $published, 'versions' => $versions];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FormVersionNotFound
     */
    public function version(OrganizerId $organizer, EventId $event, int $number): array
    {
        $this->assertEvent($organizer, $event);
        $row = $this->connection->fetchAssociative(
            'SELECT id, number, schema, schema_sha256, published_at, created_at, updated_at FROM catalog.form_version
              WHERE organizer_id = :organizer AND event_id = :event AND number = :number',
            ['organizer' => $organizer->toString(), 'event' => $event->toString(), 'number' => $number],
        );
        if (false === $row) {
            throw new FormVersionNotFoundByNumber($number);
        }

        return $this->view($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function view(array $row): array
    {
        return [
            'id' => Hydrate::string($row, 'id'),
            'number' => Hydrate::int($row, 'number'),
            'published' => null !== $row['published_at'],
            'publishedAt' => Hydrate::nullableTime($row, 'published_at'),
            'schemaSha256' => Hydrate::nullableString($row, 'schema_sha256'),
            'updatedAt' => Hydrate::time($row, 'updated_at'),
            'fields' => $this->presenter->fields(FormSchema::fromArray(Hydrate::json($row, 'schema'))),
        ];
    }

    private function assertEvent(OrganizerId $organizer, EventId $event): void
    {
        if (!$this->events->exists($organizer, $event)) {
            throw EventNotFound::forId($event);
        }
    }
}
