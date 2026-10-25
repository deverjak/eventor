<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\BucketOccupancy;
use Eventor\Registration\Contract\CapacityQuery;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Exact counters straight from `capacity_bucket` (CAP-03): the Contract `CapacityQuery` and the richer read model of
 * the admin endpoint. DBAL, explicit organizer, no cache.
 */
#[AsAlias(CapacityQuery::class)]
final readonly class CapacityBucketQuery implements CapacityQuery
{
    private const string COLUMNS = 'id, event_id, scope_type, scope_id, unit, "limit", held, confirmed';

    public function __construct(private Connection $connection) {}

    public function bucket(OrganizerId $organizer, CapacityBucketId $bucket): BucketOccupancy
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM registration.capacity_bucket WHERE id = :id AND organizer_id = :organizer',
            ['id' => $bucket->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $row) {
            throw CapacityBucketNotFound::forId($bucket);
        }

        return self::detail($row)->occupancy();
    }

    public function forEvent(OrganizerId $organizer, EventId $event): array
    {
        return array_map(static fn(BucketDetail $detail): BucketOccupancy => $detail->occupancy(), $this->detailsForEvent($organizer, $event));
    }

    /**
     * Every bucket of the event, ordered event -> activity -> variant -> wave.
     *
     * @return list<BucketDetail>
     */
    public function detailsForEvent(OrganizerId $organizer, EventId $event): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS." FROM registration.capacity_bucket
              WHERE organizer_id = :organizer AND event_id = :event
              ORDER BY CASE scope_type WHEN 'event' THEN 0 WHEN 'activity' THEN 1 WHEN 'variant' THEN 2 ELSE 3 END, id",
            ['organizer' => $organizer->toString(), 'event' => $event->toString()],
        );

        return array_map(self::detail(...), $rows);
    }

    /**
     * @param list<CapacityBucketId> $buckets
     *
     * @return list<EventId> the events that own them (each once)
     */
    public function eventsOf(array $buckets): array
    {
        if ([] === $buckets) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT event_id FROM registration.capacity_bucket WHERE id IN (:ids)',
            ['ids' => array_map(static fn(CapacityBucketId $id): string => $id->toString(), $buckets)],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_map(static fn(string $id): EventId => EventId::fromString($id), $ids);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function detail(array $row): BucketDetail
    {
        \assert(\is_string($row['id']) && \is_string($row['event_id']) && \is_string($row['scope_type']) && \is_string($row['scope_id']) && \is_string($row['unit']));
        \assert(is_numeric($row['held']) && is_numeric($row['confirmed']));

        return new BucketDetail(
            CapacityBucketId::fromString($row['id']),
            EventId::fromString($row['event_id']),
            CapacityScope::from($row['scope_type']),
            $row['scope_id'],
            CapacityUnit::from($row['unit']),
            null === $row['limit'] || !is_numeric($row['limit']) ? null : (int) $row['limit'],
            (int) $row['held'],
            (int) $row['confirmed'],
        );
    }
}
