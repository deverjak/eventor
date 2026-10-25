<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The only writer of the `held` / `confirmed` counters (ADR 002). Every method runs inside the transaction of the
 * caller (a handler on `command.bus`) and never opens, commits or rolls back one: an exception means "roll
 * everything back", which is what makes a claim over several buckets atomic (S02). Reads the database, never the
 * availability cache.
 */
interface CapacityAllocator
{
    /**
     * Holds or confirms seats in all buckets of an order, all or nothing. Buckets are locked in ascending id order.
     *
     * @param list<SeatClaim> $claims every claim of every participation of the order
     *
     * @throws CapacityExhausted      the first full bucket (in lock order); `waitlistAvailable` as given
     * @throws CapacityBucketNotFound a bucket does not exist for this organizer
     */
    public function claim(OrganizerId $organizer, array $claims, bool $waitlistAvailable = true): void;

    /**
     * held -> confirmed on every bucket of the participation (payment arrived). Idempotent for confirmed claims.
     *
     * @throws CapacityInvariantViolated no claims, or only released ones
     */
    public function confirm(OrganizerId $organizer, ParticipationId $participation): void;

    /**
     * Gives the seats of the participations back (cancel, expiry), whatever counter they are in. Idempotent:
     * already released claims are skipped. The caller announces the result (see CapacityReleaser).
     *
     * @param list<ParticipationId> $participations
     */
    public function release(OrganizerId $organizer, array $participations): ReleasedCapacity;

    /**
     * Takes released seats back after a late payment (CAP-05, S06), as confirmed unless told otherwise.
     * No-op when nothing of the participation is released.
     *
     * @throws CapacityExhausted a bucket is full again
     */
    public function reclaim(OrganizerId $organizer, ParticipationId $participation, ClaimKind $as = ClaimKind::Confirmed, bool $waitlistAvailable = true): void;

    /**
     * Advisory pre-check for guards and exception items: is there room in every bucket for the released seats of
     * this participation? It does not lock, so `reclaim()` stays the authority.
     */
    public function canReclaim(OrganizerId $organizer, ParticipationId $participation): bool;
}
