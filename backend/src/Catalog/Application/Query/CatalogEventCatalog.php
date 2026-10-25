<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\ActivitySnapshot;
use Eventor\Catalog\Contract\ActivitySummary;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\EventSummary;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\PublishedEventSnapshot;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Contract\VariantSnapshot;
use Eventor\Catalog\Contract\VenueView;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The Catalog side of the Registration, CheckIn, Communication and Content use cases (chapter 02): the published offer
 * and the display data of events. DBAL with an explicit organizer, because the callers (public zone, subscribers) have
 * no tenant. A draft is invisible here.
 */
#[AsAlias(EventCatalog::class)]
final readonly class CatalogEventCatalog implements EventCatalog
{
    private const string EVENT_COLUMNS = 'id, organizer_id, slug, name, timezone, starts_at, ends_at, publication_state, registration_state, registration_opens_at, registration_closes_at, age_reference_date, capacity_bucket_id, payment_rules, venues, price_list_version';

    public function __construct(
        private Connection $connection,
        private PublishedFormReader $forms,
    ) {}

    public function resolvePublishedSlug(OrganizerId $organizer, string $eventSlug): EventId
    {
        $id = $this->connection->fetchOne(
            "SELECT id FROM catalog.event WHERE organizer_id = :organizer AND slug = :slug AND publication_state <> 'draft'",
            ['organizer' => $organizer->toString(), 'slug' => $eventSlug],
        );
        if (!\is_string($id)) {
            throw EventNotFound::forSlug($eventSlug);
        }

        return EventId::fromString($id);
    }

    public function registrationSnapshot(OrganizerId $organizer, EventId $event): PublishedEventSnapshot
    {
        $row = $this->eventRow($organizer, $event);
        $rules = PaymentRules::fromArray(Hydrate::json($row, 'payment_rules'));
        $timezone = new \DateTimeZone(Hydrate::string($row, 'timezone'));
        $startsAt = Hydrate::time($row, 'starts_at');
        $referenceDate = Hydrate::nullableString($row, 'age_reference_date');

        $variantsByActivity = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT id, activity_id, name, price_minor, price_currency, capacity_bucket_id, capacity_unit, team_size, min_age, max_age FROM catalog.variant WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ) as $variant) {
            $variantsByActivity[Hydrate::string($variant, 'activity_id')][] = new VariantSnapshot(
                VariantId::fromString(Hydrate::string($variant, 'id')),
                ActivityId::fromString(Hydrate::string($variant, 'activity_id')),
                Hydrate::string($variant, 'name'),
                new Money(Hydrate::int($variant, 'price_minor'), Currency::from(trim(Hydrate::string($variant, 'price_currency')))),
                CapacityBucketId::fromString(Hydrate::string($variant, 'capacity_bucket_id')),
                CapacityUnit::from(Hydrate::string($variant, 'capacity_unit')),
                Hydrate::nullableInt($variant, 'team_size'),
                Hydrate::nullableInt($variant, 'min_age'),
                Hydrate::nullableInt($variant, 'max_age'),
            );
        }

        $activities = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT id, name, starts_at, ends_at, registration_state, min_age, max_age, minors_only, capacity_bucket_id FROM catalog.activity WHERE organizer_id = :organizer AND event_id = :event ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        ) as $activity) {
            $activities[] = new ActivitySnapshot(
                ActivityId::fromString(Hydrate::string($activity, 'id')),
                Hydrate::string($activity, 'name'),
                Hydrate::nullableTime($activity, 'starts_at'),
                Hydrate::nullableTime($activity, 'ends_at'),
                RegistrationState::from(Hydrate::string($activity, 'registration_state')),
                Hydrate::nullableInt($activity, 'min_age'),
                Hydrate::nullableInt($activity, 'max_age'),
                Hydrate::bool($activity, 'minors_only'),
                CapacityBucketId::fromString(Hydrate::string($activity, 'capacity_bucket_id')),
                $variantsByActivity[Hydrate::string($activity, 'id')] ?? [],
                [],
            );
        }

        return new PublishedEventSnapshot(
            $organizer,
            $event,
            Hydrate::string($row, 'slug'),
            Hydrate::string($row, 'name'),
            Hydrate::string($row, 'timezone'),
            $startsAt,
            Hydrate::time($row, 'ends_at'),
            PublicationState::from(Hydrate::string($row, 'publication_state')),
            RegistrationState::from(Hydrate::string($row, 'registration_state')),
            Hydrate::nullableTime($row, 'registration_opens_at'),
            Hydrate::nullableTime($row, 'registration_closes_at'),
            $this->forms->current($organizer, $event)->id,
            Hydrate::int($row, 'price_list_version'),
            $rules->currency,
            $rules->methods,
            $rules->holdTtlHours,
            $rules->dueDays,
            $rules->autoRestoreOnLatePayment,
            $rules->selfServiceCancellationAllowed,
            $rules->selfServiceCancellationDeadline,
            null === $referenceDate ? $startsAt->setTimezone($timezone)->setTime(0, 0) : new \DateTimeImmutable($referenceDate),
            CapacityBucketId::fromString(Hydrate::string($row, 'capacity_bucket_id')),
            $activities,
            [],
        );
    }

    public function summary(OrganizerId $organizer, EventId $event): EventSummary
    {
        return $this->summaries($organizer, [$event])[$event->toString()] ?? throw EventNotFound::forId($event);
    }

    public function summaries(OrganizerId $organizer, array $events): array
    {
        if ([] === $events) {
            return [];
        }
        $ids = array_map(static fn(EventId $id): string => $id->toString(), $events);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::EVENT_COLUMNS." FROM catalog.event WHERE organizer_id = :organizer AND id IN (:ids) AND publication_state <> 'draft'",
            ['organizer' => $organizer->toString(), 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $activitiesByEvent = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT id, event_id, name, starts_at, ends_at, registration_state FROM catalog.activity WHERE organizer_id = :organizer AND event_id IN (:ids) ORDER BY position, created_at',
            ['organizer' => $organizer->toString(), 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        ) as $activity) {
            $activitiesByEvent[Hydrate::string($activity, 'event_id')][] = new ActivitySummary(
                ActivityId::fromString(Hydrate::string($activity, 'id')),
                Hydrate::string($activity, 'name'),
                Hydrate::nullableTime($activity, 'starts_at'),
                Hydrate::nullableTime($activity, 'ends_at'),
                RegistrationState::from(Hydrate::string($activity, 'registration_state')),
            );
        }

        $summaries = [];
        foreach ($rows as $row) {
            $venues = Hydrate::json($row, 'venues');
            $first = \is_array($venues[0] ?? null) ? $venues[0] : null;
            $summaries[Hydrate::string($row, 'id')] = new EventSummary(
                EventId::fromString(Hydrate::string($row, 'id')),
                $organizer,
                Hydrate::string($row, 'slug'),
                Hydrate::string($row, 'name'),
                Hydrate::string($row, 'timezone'),
                Hydrate::time($row, 'starts_at'),
                Hydrate::time($row, 'ends_at'),
                PublicationState::from(Hydrate::string($row, 'publication_state')),
                RegistrationState::from(Hydrate::string($row, 'registration_state')),
                null === $first ? null : new VenueView(
                    \is_string($first['name'] ?? null) ? $first['name'] : '',
                    \is_string($first['address'] ?? null) ? $first['address'] : null,
                    \is_string($first['mapUrl'] ?? null) ? $first['mapUrl'] : null,
                ),
                $activitiesByEvent[Hydrate::string($row, 'id')] ?? [],
            );
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(OrganizerId $organizer, EventId $event): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::EVENT_COLUMNS." FROM catalog.event WHERE organizer_id = :organizer AND id = :id AND publication_state <> 'draft'",
            ['organizer' => $organizer->toString(), 'id' => $event->toString()],
        );
        if (false === $row) {
            throw EventNotFound::forId($event);
        }

        return $row;
    }
}
