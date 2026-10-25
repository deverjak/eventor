<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Helpers for the capacity tests: an organizer id (buckets carry no foreign key to Identity), buckets created
 * through the real `CapacityProvisioner`, claims through the real `CapacityAllocator`.
 */
abstract class CapacityTestCase extends IntegrationTestCase
{
    protected OrganizerId $organizer;
    protected EventId $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = OrganizerId::generate();
        $this->event = EventId::generate();
    }

    protected function allocator(): CapacityAllocator
    {
        return $this->service(CapacityAllocator::class);
    }

    protected function provisioner(): CapacityProvisioner
    {
        return $this->service(CapacityProvisioner::class);
    }

    protected function bucket(?int $limit, CapacityScope $scope = CapacityScope::Activity, ?EventId $event = null, ?OrganizerId $organizer = null): CapacityBucketId
    {
        $id = CapacityBucketId::generate();
        $this->provisioner()->provision($organizer ?? $this->organizer, $event ?? $this->event, [
            new CapacityBucketSpec($id, $scope, EventId::generate()->toString(), $limit, CapacityUnit::Seat),
        ]);

        return $id;
    }

    /**
     * @param list<CapacityBucketId> $buckets
     */
    protected function claim(array $buckets, int $seats = 1, ClaimKind $kind = ClaimKind::Held, ?ParticipationId $participation = null): ParticipationId
    {
        $participation ??= ParticipationId::generate();
        $this->allocator()->claim($this->organizer, array_map(static fn(CapacityBucketId $id): SeatClaim => new SeatClaim($participation, $id, $seats, $kind), $buckets));

        return $participation;
    }

    /**
     * @return array{limit: int|null, held: int, confirmed: int, overbooked_allowed: bool}
     */
    protected function counters(CapacityBucketId $bucket): array
    {
        $from = ' FROM registration.capacity_bucket WHERE id = ?';
        $params = [$bucket->toString()];
        $limit = $this->connection()->fetchOne('SELECT "limit"'.$from, $params);

        return [
            'limit' => is_numeric($limit) ? (int) $limit : null,
            'held' => $this->dbInt('SELECT held'.$from, $params),
            'confirmed' => $this->dbInt('SELECT confirmed'.$from, $params),
            'overbooked_allowed' => true === $this->connection()->fetchOne('SELECT overbooked_allowed'.$from, $params),
        ];
    }

    protected function claimStatus(ParticipationId $participation, CapacityBucketId $bucket): string
    {
        return $this->dbString('SELECT status FROM registration.capacity_claim WHERE participation_id = ? AND bucket_id = ?', [$participation->toString(), $bucket->toString()]);
    }

    protected function assertNoDrift(): void
    {
        self::assertSame([], $this->dbRows(
            "SELECT b.id FROM registration.capacity_bucket b
               LEFT JOIN registration.capacity_claim c ON c.bucket_id = b.id
              GROUP BY b.id
             HAVING b.held <> COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'held'), 0)
                 OR b.confirmed <> COALESCE(SUM(c.seats) FILTER (WHERE c.status = 'confirmed'), 0)",
        ), 'the counters must equal the sums of the claims');
    }
}
