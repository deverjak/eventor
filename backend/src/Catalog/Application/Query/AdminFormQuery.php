<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\FormSchemaPresenter;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Application\Query\View\FormOverviewView;
use Eventor\Catalog\Application\Query\View\FormVersionSummaryView;
use Eventor\Catalog\Application\Query\View\FormVersionView;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Domain\Exception\FormVersionNotFoundByNumber;
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
     * @throws EventNotFound
     */
    public function overview(OrganizerId $organizer, EventId $event): FormOverviewView
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
            $publishedAt = Hydrate::nullableTime($row, 'published_at');
            if (null !== $publishedAt) {
                $versions[] = new FormVersionSummaryView(Hydrate::string($row, 'id'), Hydrate::int($row, 'number'), $publishedAt, Hydrate::nullableString($row, 'schema_sha256'));
                $published ??= $this->view($row);
            } else {
                $draft ??= $this->view($row);
            }
        }

        return new FormOverviewView($draft, $published, $versions);
    }

    /**
     * @throws EventNotFound
     * @throws FormVersionNotFoundByNumber
     */
    public function version(OrganizerId $organizer, EventId $event, int $number): FormVersionView
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
     */
    private function view(array $row): FormVersionView
    {
        $publishedAt = Hydrate::nullableTime($row, 'published_at');

        return new FormVersionView(
            Hydrate::string($row, 'id'),
            Hydrate::int($row, 'number'),
            null !== $publishedAt,
            $publishedAt,
            Hydrate::nullableString($row, 'schema_sha256'),
            Hydrate::time($row, 'updated_at'),
            $this->presenter->fields(FormSchema::fromArray(Hydrate::json($row, 'schema'))),
        );
    }

    private function assertEvent(OrganizerId $organizer, EventId $event): void
    {
        if (!$this->events->exists($organizer, $event)) {
            throw EventNotFound::forId($event);
        }
    }
}
