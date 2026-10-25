<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Capacity;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Domain\Capacity\BucketDemand;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\CapacityInvariantViolated;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\ClaimPlan;
use Eventor\Registration\Domain\Capacity\ClaimStatus;
use Eventor\Registration\Domain\Capacity\ReleasedCapacity;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * ADR 002 in SQL. One conditional UPDATE per bucket, buckets in ascending id order, all inside the transaction the
 * caller already runs. No `SELECT ... FOR UPDATE` on buckets, no ORM entity for the counters (the unit of work would
 * hold stale numbers) and no cache: this class reads and writes the tables only.
 *
 * Claim rows of existing participations are locked first (`FOR UPDATE`, ordered by bucket and participation), so
 * confirm / release / reclaim of the same participation serialise on its claims before they touch a counter.
 */
#[AsAlias(CapacityAllocator::class)]
final readonly class DbalCapacityAllocator implements CapacityAllocator
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function claim(OrganizerId $organizer, array $claims, bool $waitlistAvailable = true): void
    {
        $plan = ClaimPlan::of($claims);

        foreach ($plan->buckets as $demand) {
            if (0 === $this->take($organizer, $demand)) {
                throw $this->whyNot($organizer, $demand->bucketId, $waitlistAvailable);
            }
        }

        $this->insertClaims($plan->claims);
    }

    public function confirm(OrganizerId $organizer, ParticipationId $participation): void
    {
        $rows = $this->lockClaims($organizer, [$participation]);
        if ([] === $rows) {
            throw CapacityInvariantViolated::noClaims($participation);
        }

        $held = array_values(array_filter($rows, static fn(array $row): bool => ClaimStatus::Held === $row['status']));
        if ([] === $held) {
            if ([] !== array_filter($rows, static fn(array $row): bool => ClaimStatus::Confirmed === $row['status'])) {
                return;
            }

            throw CapacityInvariantViolated::notHeld($participation);
        }

        foreach ($held as $row) {
            $moved = $this->connection->executeStatement(
                'UPDATE registration.capacity_bucket SET held = held - :seats, confirmed = confirmed + :seats WHERE id = :id AND held >= :seats',
                ['seats' => $row['seats'], 'id' => $row['bucket_id']],
            );
            if (0 === $moved) {
                throw CapacityInvariantViolated::counterDrift($row['bucket_id']);
            }
            $this->setStatus($participation->toString(), $row['bucket_id'], ClaimStatus::Confirmed);
        }
    }

    public function release(OrganizerId $organizer, array $participations): ReleasedCapacity
    {
        if ([] === $participations) {
            return ReleasedCapacity::none();
        }

        $held = [];
        $confirmed = [];
        $perParticipation = [];
        $active = [];
        foreach ($this->lockClaims($organizer, $participations) as $row) {
            if (!$row['status']->isActive()) {
                continue;
            }
            $active[] = $row;
            $held[$row['bucket_id']] ??= 0;
            $confirmed[$row['bucket_id']] ??= 0;
            if (ClaimStatus::Held === $row['status']) {
                $held[$row['bucket_id']] += $row['seats'];
            } else {
                $confirmed[$row['bucket_id']] += $row['seats'];
            }
            $perParticipation[$row['participation_id']] = max($perParticipation[$row['participation_id']] ?? 0, $row['seats']);
        }

        if ([] === $active) {
            return ReleasedCapacity::none();
        }

        // `lockClaims` is ordered by bucket id, and PHP arrays keep insertion order, so the buckets are updated in lock order.
        foreach ($held as $bucketId => $heldSeats) {
            $confirmedSeats = $confirmed[$bucketId];
            $freed = $this->connection->executeStatement(
                'UPDATE registration.capacity_bucket
                    SET held = held - :held,
                        confirmed = confirmed - :confirmed,
                        overbooked_allowed = (overbooked_allowed AND "limit" IS NOT NULL AND held + confirmed - :held - :confirmed > "limit")
                  WHERE id = :id AND held >= :held AND confirmed >= :confirmed',
                ['held' => $heldSeats, 'confirmed' => $confirmedSeats, 'id' => $bucketId],
            );
            if (0 === $freed) {
                throw CapacityInvariantViolated::counterDrift($bucketId);
            }
        }

        foreach ($active as $row) {
            $this->setStatus($row['participation_id'], $row['bucket_id'], ClaimStatus::Released);
        }

        return new ReleasedCapacity(
            array_map(static fn(string $id): CapacityBucketId => CapacityBucketId::fromString($id), array_keys($held)),
            array_sum($perParticipation),
        );
    }

    public function reclaim(OrganizerId $organizer, ParticipationId $participation, ClaimKind $as = ClaimKind::Confirmed, bool $waitlistAvailable = true): void
    {
        $rows = $this->lockClaims($organizer, [$participation]);
        if ([] === $rows) {
            throw CapacityInvariantViolated::noClaims($participation);
        }

        foreach ($rows as $row) {
            if (ClaimStatus::Released !== $row['status']) {
                continue;
            }

            $bucketId = CapacityBucketId::fromString($row['bucket_id']);
            $demand = new BucketDemand($bucketId, ClaimKind::Held === $as ? $row['seats'] : 0, ClaimKind::Confirmed === $as ? $row['seats'] : 0);
            if (0 === $this->take($organizer, $demand)) {
                throw $this->whyNot($organizer, $bucketId, $waitlistAvailable);
            }
            $this->setStatus($participation->toString(), $row['bucket_id'], $as->status());
        }
    }

    public function canReclaim(OrganizerId $organizer, ParticipationId $participation): bool
    {
        $fits = $this->connection->fetchOne(
            'SELECT bool_and(b."limit" IS NULL OR b.held + b.confirmed + c.seats <= b."limit")
               FROM registration.capacity_claim c
               JOIN registration.capacity_bucket b ON b.id = c.bucket_id
              WHERE c.participation_id = :participation AND b.organizer_id = :organizer AND c.status = :released',
            ['participation' => $participation->toString(), 'organizer' => $organizer->toString(), 'released' => ClaimStatus::Released->value],
        );

        return true === $fits;
    }

    /**
     * The conditional UPDATE of ADR 002: succeeds only while the bucket has room for all the seats.
     */
    private function take(OrganizerId $organizer, BucketDemand $demand): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE registration.capacity_bucket
                SET held = held + :held, confirmed = confirmed + :confirmed
              WHERE id = :id AND organizer_id = :organizer
                AND ("limit" IS NULL OR held + confirmed + :total <= "limit")',
            [
                'held' => $demand->held,
                'confirmed' => $demand->confirmed,
                'total' => $demand->total(),
                'id' => $demand->bucketId->toString(),
                'organizer' => $organizer->toString(),
            ],
        );
    }

    private function whyNot(OrganizerId $organizer, CapacityBucketId $bucket, bool $waitlistAvailable): \Throwable
    {
        $scope = $this->connection->fetchOne(
            'SELECT scope_type FROM registration.capacity_bucket WHERE id = :id AND organizer_id = :organizer',
            ['id' => $bucket->toString(), 'organizer' => $organizer->toString()],
        );

        if (!\is_string($scope)) {
            return CapacityBucketNotFound::forId($bucket);
        }

        return new CapacityExhausted($bucket, CapacityScope::from($scope), $waitlistAvailable);
    }

    /**
     * @param list<SeatClaim> $claims already in lock order
     */
    private function insertClaims(array $claims): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:sP');
        $values = [];
        $params = ['now' => $now];
        foreach ($claims as $i => $claim) {
            $values[] = \sprintf('(:p%1$d, :b%1$d, :s%1$d, :k%1$d, :now)', $i);
            $params['p'.$i] = $claim->participationId->toString();
            $params['b'.$i] = $claim->bucketId->toString();
            $params['s'.$i] = $claim->seats;
            $params['k'.$i] = $claim->kind->value;
        }

        $inserted = $this->connection->executeStatement(
            'INSERT INTO registration.capacity_claim (participation_id, bucket_id, seats, status, created_at) VALUES '
            .implode(', ', $values).' ON CONFLICT (participation_id, bucket_id) DO NOTHING',
            $params,
        );

        if ($inserted !== \count($claims)) {
            throw CapacityInvariantViolated::duplicateClaim();
        }
    }

    private function setStatus(string $participationId, string $bucketId, ClaimStatus $status): void
    {
        $this->connection->executeStatement(
            'UPDATE registration.capacity_claim SET status = :status WHERE participation_id = :participation AND bucket_id = :bucket',
            ['status' => $status->value, 'participation' => $participationId, 'bucket' => $bucketId],
        );
    }

    /**
     * Locks the claims of the participations in the global order (bucket id, participation id).
     *
     * @param list<ParticipationId> $participations
     *
     * @return list<array{participation_id: string, bucket_id: string, seats: int, status: ClaimStatus}>
     */
    private function lockClaims(OrganizerId $organizer, array $participations): array
    {
        /** @var list<array{participation_id: string, bucket_id: string, seats: int|string, status: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.participation_id, c.bucket_id, c.seats, c.status
               FROM registration.capacity_claim c
               JOIN registration.capacity_bucket b ON b.id = c.bucket_id
              WHERE b.organizer_id = :organizer AND c.participation_id IN (:participations)
              ORDER BY c.bucket_id, c.participation_id
                FOR UPDATE OF c',
            ['organizer' => $organizer->toString(), 'participations' => array_map(static fn(ParticipationId $id): string => $id->toString(), $participations)],
            ['participations' => ArrayParameterType::STRING],
        );

        return array_map(static fn(array $row): array => [
            'participation_id' => $row['participation_id'],
            'bucket_id' => $row['bucket_id'],
            'seats' => (int) $row['seats'],
            'status' => ClaimStatus::from($row['status']),
        ], $rows);
    }
}
