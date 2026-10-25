<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * The pure part of an atomic claim (ADR 002, steps 1 and 2): the seats of all participations aggregated per bucket
 * and the buckets in the one deterministic lock order, ascending by id. Every code path that touches several
 * buckets in one transaction must go through this order, otherwise two orders that overlap in opposite order
 * deadlock (S02).
 */
final readonly class ClaimPlan
{
    /**
     * @param list<BucketDemand> $buckets ascending by bucket id
     * @param list<SeatClaim>    $claims  ascending by bucket id, then participation id
     */
    private function __construct(
        public array $buckets,
        public array $claims,
    ) {}

    /**
     * @param list<SeatClaim> $claims
     */
    public static function of(array $claims): self
    {
        if ([] === $claims) {
            throw new \InvalidArgumentException('Nothing to claim.');
        }

        $seen = [];
        $held = [];
        $confirmed = [];
        foreach ($claims as $claim) {
            $key = $claim->bucketId->toString().'|'.$claim->participationId->toString();
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException(\sprintf('Participation %s claims bucket %s twice.', $claim->participationId, $claim->bucketId));
            }
            $seen[$key] = true;

            $bucket = $claim->bucketId->toString();
            $held[$bucket] ??= 0;
            $confirmed[$bucket] ??= 0;
            if (ClaimKind::Held === $claim->kind) {
                $held[$bucket] += $claim->seats;
            } else {
                $confirmed[$bucket] += $claim->seats;
            }
        }

        $ids = array_keys($held);
        sort($ids, \SORT_STRING);

        $demands = [];
        foreach ($ids as $id) {
            $demands[] = new BucketDemand(CapacityBucketId::fromString($id), $held[$id], $confirmed[$id]);
        }

        usort($claims, static fn(SeatClaim $a, SeatClaim $b): int => [$a->bucketId->toString(), $a->participationId->toString()] <=> [$b->bucketId->toString(), $b->participationId->toString()]);

        return new self($demands, $claims);
    }
}
