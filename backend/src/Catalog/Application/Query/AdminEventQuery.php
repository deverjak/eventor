<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Application\Presenter\Hydrate;
use Eventor\Catalog\Application\Query\View\ActivityDetailView;
use Eventor\Catalog\Application\Query\View\ContactView;
use Eventor\Catalog\Application\Query\View\EventDetailView;
use Eventor\Catalog\Application\Query\View\EventListItemView;
use Eventor\Catalog\Application\Query\View\EventListView;
use Eventor\Catalog\Application\Query\View\PaymentRulesView;
use Eventor\Catalog\Application\Query\View\VariantDetailView;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\VenueView;
use Eventor\Catalog\Domain\Structure\PaymentRules;
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

    public function list(OrganizerId $organizer): EventListView
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name, short_description, starts_at, ends_at, visibility, publication_state, registration_state, version
               FROM catalog.event WHERE organizer_id = :organizer ORDER BY starts_at DESC, created_at DESC',
            ['organizer' => $organizer->toString()],
        );

        return new EventListView(array_map(static fn(array $row): EventListItemView => new EventListItemView(
            Hydrate::string($row, 'id'),
            Hydrate::string($row, 'slug'),
            Hydrate::string($row, 'name'),
            Hydrate::nullableString($row, 'short_description'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::time($row, 'ends_at'),
            Hydrate::string($row, 'visibility'),
            Hydrate::string($row, 'publication_state'),
            Hydrate::string($row, 'registration_state'),
            Hydrate::int($row, 'version'),
        ), $rows));
    }

    /**
     * @throws EventNotFound
     */
    public function detail(OrganizerId $organizer, EventId $event): EventDetailView
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
            $variants[Hydrate::string($variant, 'activity_id')][] = new VariantDetailView(
                Hydrate::string($variant, 'id'),
                Hydrate::string($variant, 'name'),
                new Money(Hydrate::int($variant, 'price_minor'), Currency::from(trim(Hydrate::string($variant, 'price_currency')))),
                Hydrate::nullableInt($variant, 'capacity_limit'),
                Hydrate::string($variant, 'capacity_bucket_id'),
                Hydrate::string($variant, 'capacity_unit'),
                Hydrate::nullableInt($variant, 'team_size'),
                Hydrate::nullableInt($variant, 'min_age'),
                Hydrate::nullableInt($variant, 'max_age'),
                Hydrate::int($variant, 'position'),
                Hydrate::int($variant, 'version'),
            );
        }

        $activities = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM catalog.activity WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ) as $activity) {
            $activities[] = new ActivityDetailView(
                Hydrate::string($activity, 'id'),
                Hydrate::string($activity, 'name'),
                Hydrate::nullableString($activity, 'description'),
                Hydrate::nullableString($activity, 'location'),
                Hydrate::nullableTime($activity, 'starts_at'),
                Hydrate::nullableTime($activity, 'ends_at'),
                Hydrate::bool($activity, 'implicit'),
                Hydrate::string($activity, 'registration_state'),
                Hydrate::nullableTime($activity, 'registration_opens_at'),
                Hydrate::nullableTime($activity, 'registration_closes_at'),
                Hydrate::nullableInt($activity, 'min_age'),
                Hydrate::nullableInt($activity, 'max_age'),
                Hydrate::bool($activity, 'minors_only'),
                Hydrate::nullableInt($activity, 'capacity_limit'),
                Hydrate::string($activity, 'capacity_bucket_id'),
                Hydrate::int($activity, 'position'),
                Hydrate::int($activity, 'version'),
                $variants[Hydrate::string($activity, 'id')] ?? [],
            );
        }

        $rules = PaymentRules::fromArray(Hydrate::json($row, 'payment_rules'));
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

        return new EventDetailView(
            Hydrate::string($row, 'id'),
            Hydrate::string($row, 'slug'),
            Hydrate::string($row, 'name'),
            Hydrate::nullableString($row, 'short_description'),
            Hydrate::nullableString($row, 'description'),
            Hydrate::nullableString($row, 'cover_image_url'),
            new ContactView(Hydrate::nullableString($row, 'contact_name'), Hydrate::nullableString($row, 'contact_email'), Hydrate::nullableString($row, 'contact_phone')),
            Hydrate::string($row, 'timezone'),
            Hydrate::time($row, 'starts_at'),
            Hydrate::time($row, 'ends_at'),
            Hydrate::nullableString($row, 'age_reference_date'),
            Hydrate::string($row, 'visibility'),
            Hydrate::string($row, 'publication_state'),
            Hydrate::string($row, 'registration_state'),
            Hydrate::nullableTime($row, 'registration_opens_at'),
            Hydrate::nullableTime($row, 'registration_closes_at'),
            Hydrate::nullableInt($row, 'capacity_limit'),
            Hydrate::string($row, 'capacity_bucket_id'),
            self::rulesView($rules),
            $venues,
            Hydrate::int($row, 'price_list_version'),
            Hydrate::nullableString($row, 'cancellation_reason'),
            Hydrate::time($row, 'created_at'),
            Hydrate::time($row, 'updated_at'),
            Hydrate::int($row, 'version'),
            $activities,
        );
    }

    public function exists(OrganizerId $organizer, EventId $event): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM catalog.event WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $event->toString()],
        );
    }

    private static function rulesView(PaymentRules $rules): PaymentRulesView
    {
        return new PaymentRulesView(
            array_map(static fn($method): string => $method->value, $rules->methods),
            $rules->dueDays,
            $rules->holdTtlHours,
            $rules->autoRestoreOnLatePayment,
            $rules->selfServiceCancellationAllowed,
            $rules->selfServiceCancellationDeadline?->format(\DATE_ATOM),
            $rules->currency->value,
        );
    }
}
