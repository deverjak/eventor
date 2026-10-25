<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Capacity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Eventor\Registration\Contract\CapacityLimitChange;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Domain\Capacity\BucketCounters;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Capacity configuration for Catalog. Runs in the caller's transaction; the buckets table is Registration's, the
 * decisions (which levels, which limits) are Catalog's. Limit changes never look at the cache.
 */
#[AsAlias(CapacityProvisioner::class)]
final readonly class DbalCapacityProvisioner implements CapacityProvisioner
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function provision(OrganizerId $organizer, EventId $event, array $buckets): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:sP');

        foreach ($buckets as $spec) {
            self::assertLimit($spec->limit);
            $this->connection->executeStatement(
                'INSERT INTO registration.capacity_bucket (id, organizer_id, event_id, scope_type, scope_id, unit, "limit", held, confirmed, overbooked_allowed, created_at)
                 VALUES (:id, :organizer, :event, :scope, :scopeId, :unit, :limit, 0, 0, FALSE, :now)
                 ON CONFLICT (id) DO NOTHING',
                [
                    'id' => $spec->bucketId->toString(),
                    'organizer' => $organizer->toString(),
                    'event' => $event->toString(),
                    'scope' => $spec->scope->value,
                    'scopeId' => $spec->scopeId,
                    'unit' => $spec->unit->value,
                    'limit' => $spec->limit,
                    'now' => $now,
                ],
                ['limit' => ParameterType::INTEGER],
            );
        }
    }

    public function changeLimit(OrganizerId $organizer, CapacityBucketId $bucket, ?int $newLimit, string $reason): CapacityLimitChange
    {
        self::assertLimit($newLimit);

        // The row lock orders concurrent limit changes of one bucket and gives a consistent "previous" value. Claims
        // do not wait for it for long: they only need the row for their own single UPDATE.
        $row = $this->connection->fetchAssociative(
            'SELECT "limit", held, confirmed FROM registration.capacity_bucket WHERE id = :id AND organizer_id = :organizer FOR UPDATE',
            ['id' => $bucket->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $row) {
            throw CapacityBucketNotFound::forId($bucket);
        }

        $previous = is_numeric($row['limit']) ? (int) $row['limit'] : null;
        $counters = new BucketCounters($previous, is_numeric($row['held']) ? (int) $row['held'] : 0, is_numeric($row['confirmed']) ? (int) $row['confirmed'] : 0);
        $below = $counters->isBelowOccupancyAfter($newLimit);

        $this->connection->executeStatement(
            'UPDATE registration.capacity_bucket SET "limit" = :limit, overbooked_allowed = :overbooked WHERE id = :id',
            ['limit' => $newLimit, 'overbooked' => $below, 'id' => $bucket->toString()],
            ['limit' => ParameterType::INTEGER, 'overbooked' => ParameterType::BOOLEAN],
        );

        return new CapacityLimitChange($bucket, $previous, $newLimit, $counters->held, $counters->confirmed, $below);
    }

    private static function assertLimit(?int $limit): void
    {
        if (null !== $limit && $limit < 0) {
            throw new \InvalidArgumentException('A capacity limit cannot be negative.');
        }
    }
}
