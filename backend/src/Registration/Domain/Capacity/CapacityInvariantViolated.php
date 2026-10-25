<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The caller asked the allocator for a transition that the claims of the participation do not allow (confirming a
 * participation without claims, claiming twice). It is a programming or workflow error, not a business outcome:
 * the surrounding transaction rolls back and the request ends as an internal error.
 */
final class CapacityInvariantViolated extends \LogicException
{
    public static function noClaims(ParticipationId $participation): self
    {
        return new self(\sprintf('Participation %s occupies no capacity.', $participation));
    }

    public static function notHeld(ParticipationId $participation): self
    {
        return new self(\sprintf('Participation %s has no held capacity to confirm (released or never claimed).', $participation));
    }

    public static function duplicateClaim(): self
    {
        return new self('A participation already has a claim on one of the buckets; use reclaim() to take released seats back.');
    }

    public static function counterDrift(string $bucketId): self
    {
        return new self(\sprintf('Counters of bucket %s are lower than its claims; run eventor:capacity:verify.', $bucketId));
    }
}
