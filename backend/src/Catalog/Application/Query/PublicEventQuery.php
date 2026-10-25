<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Catalog side of the public event page (PUB-01, PUB-02, PUB-03): description, activities, variants with prices, the
 * public program, the news and the public form schema. Availability per bucket comes from Registration's
 * `AvailabilityReader`; where the facade is not wired the page simply has no availability. A draft is "not found",
 * an unlisted event is served to whoever has the link but not listed.
 */
final readonly class PublicEventQuery
{
    public function __construct(
        private Connection $connection,
        private CatalogEventCatalog $catalog,
        private PublishedFormReader $forms,
        private FormSchemaPresenter $presenter,
        private CatalogProgramReader $program,
        private ?AvailabilityReader $availability = null,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(OrganizerId $organizer): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT slug, name, short_description, cover_image_url, starts_at, ends_at, publication_state, registration_state
               FROM catalog.event
              WHERE organizer_id = :organizer AND visibility = 'public' AND publication_state IN ('published', 'completed')
              ORDER BY starts_at DESC",
            ['organizer' => $organizer->toString()],
        );

        return array_map(static fn(array $row): array => [
            'slug' => Hydrate::string($row, 'slug'),
            'name' => Hydrate::string($row, 'name'),
            'shortDescription' => Hydrate::nullableString($row, 'short_description'),
            'coverImageUrl' => Hydrate::nullableString($row, 'cover_image_url'),
            'startsAt' => Hydrate::time($row, 'starts_at'),
            'endsAt' => Hydrate::time($row, 'ends_at'),
            'publicationState' => Hydrate::string($row, 'publication_state'),
            'registrationState' => Hydrate::string($row, 'registration_state'),
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws EventNotFound
     */
    public function detail(OrganizerId $organizer, string $eventSlug): array
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
            $variants[Hydrate::string($variant, 'activity_id')][] = [
                'id' => Hydrate::string($variant, 'id'),
                'name' => Hydrate::string($variant, 'name'),
                'price' => new Money(Hydrate::int($variant, 'price_minor'), Currency::from(trim(Hydrate::string($variant, 'price_currency')))),
                'teamSize' => Hydrate::nullableInt($variant, 'team_size'),
                'capacityUnit' => Hydrate::string($variant, 'capacity_unit'),
                'minAge' => Hydrate::nullableInt($variant, 'min_age'),
                'maxAge' => Hydrate::nullableInt($variant, 'max_age'),
                'availability' => $this->availabilityOf($free, Hydrate::string($variant, 'capacity_bucket_id')),
            ];
        }

        $activities = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.activity WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $eventId->toString()],
        ) as $activity) {
            $activities[] = [
                'id' => Hydrate::string($activity, 'id'),
                'name' => Hydrate::string($activity, 'name'),
                'description' => Hydrate::nullableString($activity, 'description'),
                'location' => Hydrate::nullableString($activity, 'location'),
                'startsAt' => Hydrate::nullableTime($activity, 'starts_at'),
                'endsAt' => Hydrate::nullableTime($activity, 'ends_at'),
                'registrationState' => Hydrate::string($activity, 'registration_state'),
                'registrationOpensAt' => Hydrate::nullableTime($activity, 'registration_opens_at'),
                'registrationClosesAt' => Hydrate::nullableTime($activity, 'registration_closes_at'),
                'minAge' => Hydrate::nullableInt($activity, 'min_age'),
                'maxAge' => Hydrate::nullableInt($activity, 'max_age'),
                'minorsOnly' => Hydrate::bool($activity, 'minors_only'),
                'availability' => $this->availabilityOf($free, Hydrate::string($activity, 'capacity_bucket_id')),
                'variants' => $variants[Hydrate::string($activity, 'id')] ?? [],
            ];
        }

        $news = array_map(static fn(array $post): array => [
            'id' => Hydrate::string($post, 'id'),
            'title' => Hydrate::string($post, 'title'),
            'body' => Hydrate::string($post, 'body'),
            'priority' => Hydrate::int($post, 'priority'),
            'publishedAt' => Hydrate::time($post, 'published_at'),
        ], $this->connection->fetchAllAssociative(
            'SELECT id, title, body, priority, published_at FROM catalog.news_post WHERE organizer_id = :organizer AND event_id = :event AND published_at IS NOT NULL ORDER BY priority DESC, published_at DESC',
            ['organizer' => $organizer->toString(), 'event' => $eventId->toString()],
        ));

        $program = array_map(static fn($item): array => [
            'id' => $item->id->toString(),
            'activityId' => $item->activityId?->toString(),
            'title' => $item->title,
            'description' => $item->description,
            'location' => $item->location,
            'startsAt' => $item->startsAt,
            'endsAt' => $item->endsAt,
        ], $this->program->forEvent($organizer, $eventId, ProgramVisibility::Public));

        return [
            'id' => $eventId->toString(),
            'slug' => Hydrate::string($row, 'slug'),
            'name' => Hydrate::string($row, 'name'),
            'shortDescription' => Hydrate::nullableString($row, 'short_description'),
            'description' => Hydrate::nullableString($row, 'description'),
            'coverImageUrl' => Hydrate::nullableString($row, 'cover_image_url'),
            'contact' => [
                'name' => Hydrate::nullableString($row, 'contact_name'),
                'email' => Hydrate::nullableString($row, 'contact_email'),
                'phone' => Hydrate::nullableString($row, 'contact_phone'),
            ],
            'timezone' => Hydrate::string($row, 'timezone'),
            'startsAt' => Hydrate::time($row, 'starts_at'),
            'endsAt' => Hydrate::time($row, 'ends_at'),
            'publicationState' => Hydrate::string($row, 'publication_state'),
            'registrationState' => Hydrate::string($row, 'registration_state'),
            'registrationOpensAt' => Hydrate::nullableTime($row, 'registration_opens_at'),
            'registrationClosesAt' => Hydrate::nullableTime($row, 'registration_closes_at'),
            'venues' => Hydrate::json($row, 'venues'),
            'paymentMethods' => array_map(static fn($method): string => $method->value, $rules->methods),
            'availability' => $this->availabilityOf($free, Hydrate::string($row, 'capacity_bucket_id')),
            'availabilityShown' => null !== $this->availability,
            'activities' => $activities,
            'program' => $program,
            'news' => $news,
            'form' => $this->form($organizer, $eventId),
        ];
    }

    /**
     * The current public form schema of a published event (ADR 007, "GET .../form").
     *
     * @return array<string, mixed>
     */
    public function formOf(OrganizerId $organizer, string $eventSlug): array
    {
        return $this->form($organizer, $this->catalog->resolvePublishedSlug($organizer, $eventSlug));
    }

    /**
     * @return array<string, mixed>
     */
    private function form(OrganizerId $organizer, EventId $event): array
    {
        $form = $this->forms->current($organizer, $event);

        return [
            'formVersionId' => $form->id->toString(),
            'number' => $form->number,
            'schemaSha256' => $form->schemaSha256,
            'fields' => $this->presenter->fields($form->schema),
        ];
    }

    /**
     * @return array<string, int|null>|null free places by bucket id (null = unlimited), null when unknown
     */
    private function freePlaces(OrganizerId $organizer, EventId $event): ?array
    {
        if (null === $this->availability) {
            return null;
        }
        $free = [];
        try {
            $buckets = $this->availability->forEvent($organizer, $event)->buckets;
        } catch (CapacityBucketNotFound) {
            return null;
        }
        foreach ($buckets as $bucket) {
            $free[$bucket->bucketId->toString()] = $bucket->available;
        }

        return $free;
    }

    /**
     * @param array<string, int|null>|null $free
     *
     * @return array{status: string, available: int|null}|null
     */
    private function availabilityOf(?array $free, string $bucketId): ?array
    {
        if (null === $free || !\array_key_exists($bucketId, $free)) {
            return null;
        }
        $available = $free[$bucketId];

        return AvailabilityView::describe($available);
    }
}
