<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\FormSchemaPresenter;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Application\Query\View\AvailabilityStatusView;
use Eventor\Catalog\Application\Query\View\ContactView;
use Eventor\Catalog\Application\Query\View\PublicActivityView;
use Eventor\Catalog\Application\Query\View\PublicEventListItemView;
use Eventor\Catalog\Application\Query\View\PublicEventListView;
use Eventor\Catalog\Application\Query\View\PublicEventPageView;
use Eventor\Catalog\Application\Query\View\PublicFormView;
use Eventor\Catalog\Application\Query\View\PublicNewsView;
use Eventor\Catalog\Application\Query\View\PublicProgramItemView;
use Eventor\Catalog\Application\Query\View\PublicVariantView;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\ProgramItemView;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Contract\VenueView;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Catalog side of the public event page (PUB-01, PUB-02, PUB-03): description, activities, variants with prices, the
 * public program, the news and the public form schema, composed from the catalogue, the published form, the program
 * and Registration's `AvailabilityReader`. Where that facade is not wired the page simply has no availability. A draft
 * is "not found"; an unlisted event is served to whoever has the link but not listed.
 */
final readonly class PublicEventPageQuery
{
    public function __construct(
        private Connection $connection,
        private EventCatalogQuery $catalog,
        private PublishedFormQuery $forms,
        private FormSchemaPresenter $presenter,
        private ProgramReaderQuery $program,
        private ?AvailabilityReader $availability = null,
    ) {}

    public function list(OrganizerId $organizer): PublicEventListView
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT slug, name, short_description, cover_image_url, starts_at, ends_at, publication_state, registration_state
               FROM catalog.event
              WHERE organizer_id = :organizer AND visibility = 'public' AND publication_state IN ('published', 'completed')
              ORDER BY starts_at DESC",
            ['organizer' => $organizer->toString()],
        );

        return new PublicEventListView(array_map(static fn(array $row): PublicEventListItemView => new PublicEventListItemView(
            Hydrate::string($row, 'slug'),
            Hydrate::string($row, 'name'),
            Hydrate::nullableString($row, 'short_description'),
            Hydrate::nullableString($row, 'cover_image_url'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::time($row, 'ends_at'),
            Hydrate::string($row, 'publication_state'),
            Hydrate::string($row, 'registration_state'),
        ), $rows));
    }

    /**
     * @throws EventNotFound
     */
    public function detail(OrganizerId $organizer, string $eventSlug): PublicEventPageView
    {
        $eventId = $this->catalog->resolvePublishedSlug($organizer, $eventSlug);
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM catalog.event WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $eventId->toString()],
        );
        if (false === $row) {
            throw EventNotFound::forSlug($eventSlug);
        }

        $free = $this->freePlaces($organizer, $eventId);
        $rules = PaymentRules::fromArray(Hydrate::json($row, 'payment_rules'));

        $variants = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.variant WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $eventId->toString()],
        ) as $variant) {
            $variants[Hydrate::string($variant, 'activity_id')][] = new PublicVariantView(
                Hydrate::string($variant, 'id'),
                Hydrate::string($variant, 'name'),
                new Money(Hydrate::int($variant, 'price_minor'), Currency::from(trim(Hydrate::string($variant, 'price_currency')))),
                Hydrate::nullableInt($variant, 'team_size'),
                Hydrate::string($variant, 'capacity_unit'),
                Hydrate::nullableInt($variant, 'min_age'),
                Hydrate::nullableInt($variant, 'max_age'),
                $this->availabilityOf($free, Hydrate::string($variant, 'capacity_bucket_id')),
            );
        }

        $activities = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.activity WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $eventId->toString()],
        ) as $activity) {
            $activities[] = new PublicActivityView(
                Hydrate::string($activity, 'id'),
                Hydrate::string($activity, 'name'),
                Hydrate::nullableString($activity, 'description'),
                Hydrate::nullableString($activity, 'location'),
                Hydrate::nullableTime($activity, 'starts_at'),
                Hydrate::nullableTime($activity, 'ends_at'),
                Hydrate::string($activity, 'registration_state'),
                Hydrate::nullableTime($activity, 'registration_opens_at'),
                Hydrate::nullableTime($activity, 'registration_closes_at'),
                Hydrate::nullableInt($activity, 'min_age'),
                Hydrate::nullableInt($activity, 'max_age'),
                Hydrate::bool($activity, 'minors_only'),
                $this->availabilityOf($free, Hydrate::string($activity, 'capacity_bucket_id')),
                $variants[Hydrate::string($activity, 'id')] ?? [],
            );
        }

        $news = array_map(static fn(array $post): PublicNewsView => new PublicNewsView(
            Hydrate::string($post, 'id'),
            Hydrate::string($post, 'title'),
            Hydrate::string($post, 'body'),
            Hydrate::int($post, 'priority'),
            Hydrate::time($post, 'published_at'),
        ), $this->connection->fetchAllAssociative(
            'SELECT id, title, body, priority, published_at FROM catalog.news_post WHERE organizer_id = :organizer AND event_id = :event AND published_at IS NOT NULL ORDER BY priority DESC, published_at DESC',
            ['organizer' => $organizer->toString(), 'event' => $eventId->toString()],
        ));

        $program = array_map(static fn(ProgramItemView $item): PublicProgramItemView => new PublicProgramItemView(
            $item->id->toString(),
            $item->activityId?->toString(),
            $item->title,
            $item->description,
            $item->location,
            $item->startsAt,
            $item->endsAt,
        ), $this->program->forEvent($organizer, $eventId, ProgramVisibility::Public));

        $venues = [];
        foreach (Hydrate::json($row, 'venues') as $venue) {
            if (\is_array($venue)) {
                $venues[] = new VenueView(
                    \is_string($venue['name'] ?? null) ? $venue['name'] : '',
                    \is_string($venue['address'] ?? null) ? $venue['address'] : null,
                    \is_string($venue['mapUrl'] ?? null) ? $venue['mapUrl'] : null,
                );
            }
        }

        return new PublicEventPageView(
            $eventId->toString(),
            Hydrate::string($row, 'slug'),
            Hydrate::string($row, 'name'),
            Hydrate::nullableString($row, 'short_description'),
            Hydrate::nullableString($row, 'description'),
            Hydrate::nullableString($row, 'cover_image_url'),
            new ContactView(Hydrate::nullableString($row, 'contact_name'), Hydrate::nullableString($row, 'contact_email'), Hydrate::nullableString($row, 'contact_phone')),
            Hydrate::string($row, 'timezone'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::time($row, 'ends_at'),
            Hydrate::string($row, 'publication_state'),
            Hydrate::string($row, 'registration_state'),
            Hydrate::nullableTime($row, 'registration_opens_at'),
            Hydrate::nullableTime($row, 'registration_closes_at'),
            $venues,
            array_map(static fn($method): string => $method->value, $rules->methods),
            $this->availabilityOf($free, Hydrate::string($row, 'capacity_bucket_id')),
            null !== $this->availability,
            $activities,
            $program,
            $news,
            $this->form($organizer, $eventId),
        );
    }

    /**
     * The current public form schema of a published event (ADR 007, "GET .../form").
     *
     * @throws EventNotFound
     */
    public function formOf(OrganizerId $organizer, string $eventSlug): PublicFormView
    {
        return $this->form($organizer, $this->catalog->resolvePublishedSlug($organizer, $eventSlug));
    }

    private function form(OrganizerId $organizer, EventId $event): PublicFormView
    {
        $form = $this->forms->current($organizer, $event);

        return new PublicFormView($form->id->toString(), $form->number, $form->schemaSha256, $this->presenter->fields($form->schema));
    }

    /**
     * @return array<string, int|null>|null free places by bucket id (null = unlimited), null when unknown
     */
    private function freePlaces(OrganizerId $organizer, EventId $event): ?array
    {
        if (null === $this->availability) {
            return null;
        }
        try {
            $buckets = $this->availability->forEvent($organizer, $event)->buckets;
        } catch (CapacityBucketNotFound) {
            return null;
        }
        $free = [];
        foreach ($buckets as $bucket) {
            $free[$bucket->bucketId->toString()] = $bucket->available;
        }

        return $free;
    }

    /**
     * @param array<string, int|null>|null $free
     */
    private function availabilityOf(?array $free, string $bucketId): ?AvailabilityStatusView
    {
        if (null === $free || !\array_key_exists($bucketId, $free)) {
            return null;
        }

        return AvailabilityStatusView::of($free[$bucketId]);
    }
}
