<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\Query;

use Doctrine\DBAL\Connection;

/**
 * The reconciliation query of ADR 002: the counters of every bucket against the sums of its active claims. The
 * counters are denormalised, so anything that bypasses the allocator shows up here as drift. It runs in tests
 * and as `eventor:capacity:verify` (the nightly Scheduler job and its AttentionItem come with the Attention wiring).
 */
final readonly class CapacityConsistencyCheck
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<CapacityDrift> empty = the counters are exactly what the claims say
     */
    public function drift(): array
    {
        /** @var list<array{id: string, event_id: string, held: int|string, confirmed: int|string, claim_held: int|string, claim_confirmed: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT b.id, b.event_id, b.held, b.confirmed,
                    COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'held'), 0) AS claim_held,
                    COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'confirmed'), 0) AS claim_confirmed
               FROM registration.capacity_bucket b
               LEFT JOIN registration.capacity_claim c ON c.bucket_id = b.id
              GROUP BY b.id
             HAVING b.held <> COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'held'), 0)
                 OR b.confirmed <> COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'confirmed'), 0)
              ORDER BY b.id",
        );

        return array_map(static fn(array $row): CapacityDrift => new CapacityDrift(
            $row['id'],
            $row['event_id'],
            (int) $row['held'],
            (int) $row['confirmed'],
            (int) $row['claim_held'],
            (int) $row['claim_confirmed'],
        ), $rows);
    }
}
