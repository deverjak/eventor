<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Read models of the event admin screens (EVT-01..11). Tenant scoping is explicit in the signature (DBAL queries are
 * not covered by the Doctrine filter, ADR 005).
 */
final readonly class AdminEventQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(OrganizerId $organizer): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name, short_description, starts_at, ends_at, visibility, publication_state, registration_state, version
               FROM catalog.event WHERE organizer_id = :organizer ORDER BY starts_at DESC, created_at DESC',
            ['organizer' => $organizer->toString()],
        );

        return array_map(static fn(array $row): array => [
            'id' => Hydrate::string($row, 'id'),
            'slug' => Hydrate::string($row, 'slug'),
            'name' => Hydrate::string($row, 'name'),
            'shortDescription' => Hydrate::nullableString($row, 'short_description'),
            'startsAt' => Hydrate::time($row, 'starts_at'),
            'endsAt' => Hydrate::time($row, 'ends_at'),
            'visibility' => Hydrate::string($row, 'visibility'),
            'publicationState' => Hydrate::string($row, 'publication_state'),
            'registrationState' => Hydrate::string($row, 'registration_state'),
            'version' => Hydrate::int($row, 'version'),
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws EventNotFound
     */
    public function detail(OrganizerId $organizer, EventId $event): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM catalog.event WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $event->toString()],
        );
        if (false === $row) {
            throw EventNotFound::forId($event);
        }

        $variants = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.variant WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ) as $variant) {
            $variants[Hydrate::string($variant, 'activity_id')][] = [
                'id' => Hydrate::string($variant, 'id'),
                'name' => Hydrate::string($variant, 'name'),
                'price' => new Money(Hydrate::int($variant, 'price_minor'), Currency::from(trim(Hydrate::string($variant, 'price_currency')))),
                'capacityLimit' => Hydrate::nullableInt($variant, 'capacity_limit'),
                'capacityBucketId' => Hydrate::string($variant, 'capacity_bucket_id'),
                'capacityUnit' => Hydrate::string($variant, 'capacity_unit'),
                'teamSize' => Hydrate::nullableInt($variant, 'team_size'),
                'minAge' => Hydrate::nullableInt($variant, 'min_age'),
                'maxAge' => Hydrate::nullableInt($variant, 'max_age'),
                'position' => Hydrate::int($variant, 'position'),
                'version' => Hydrate::int($variant, 'version'),
            ];
        }

        $activities = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.activity WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ) as $activity) {
            $activities[] = [
                'id' => Hydrate::string($activity, 'id'),
                'name' => Hydrate::string($activity, 'name'),
                'description' => Hydrate::nullableString($activity, 'description'),
                'location' => Hydrate::nullableString($activity, 'location'),
                'startsAt' => Hydrate::nullableTime($activity, 'starts_at'),
                'endsAt' => Hydrate::nullableTime($activity, 'ends_at'),
                'implicit' => Hydrate::bool($activity, 'implicit'),
                'registrationState' => Hydrate::string($activity, 'registration_state'),
                'registrationOpensAt' => Hydrate::nullableTime($activity, 'registration_opens_at'),
                'registrationClosesAt' => Hydrate::nullableTime($activity, 'registration_closes_at'),
                'minAge' => Hydrate::nullableInt($activity, 'min_age'),
                'maxAge' => Hydrate::nullableInt($activity, 'max_age'),
                'minorsOnly' => Hydrate::bool($activity, 'minors_only'),
                'capacityLimit' => Hydrate::nullableInt($activity, 'capacity_limit'),
                'capacityBucketId' => Hydrate::string($activity, 'capacity_bucket_id'),
                'position' => Hydrate::int($activity, 'position'),
                'version' => Hydrate::int($activity, 'version'),
                'variants' => $variants[Hydrate::string($activity, 'id')] ?? [],
            ];
        }

        return [
            'id' => Hydrate::string($row, 'id'),
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
            'ageReferenceDate' => Hydrate::nullableString($row, 'age_reference_date'),
            'visibility' => Hydrate::string($row, 'visibility'),
            'publicationState' => Hydrate::string($row, 'publication_state'),
            'registrationState' => Hydrate::string($row, 'registration_state'),
            'registrationOpensAt' => Hydrate::nullableTime($row, 'registration_opens_at'),
            'registrationClosesAt' => Hydrate::nullableTime($row, 'registration_closes_at'),
            'capacityLimit' => Hydrate::nullableInt($row, 'capacity_limit'),
            'capacityBucketId' => Hydrate::string($row, 'capacity_bucket_id'),
            'paymentRules' => Hydrate::json($row, 'payment_rules'),
            'venues' => Hydrate::json($row, 'venues'),
            'priceListVersion' => Hydrate::int($row, 'price_list_version'),
            'cancellationReason' => Hydrate::nullableString($row, 'cancellation_reason'),
            'createdAt' => Hydrate::time($row, 'created_at'),
            'updatedAt' => Hydrate::time($row, 'updated_at'),
            'version' => Hydrate::int($row, 'version'),
            'activities' => $activities,
        ];
    }

    public function exists(OrganizerId $organizer, EventId $event): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM catalog.event WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $event->toString()],
        );
    }
}
