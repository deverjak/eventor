<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

/**
 * What a bucket looks like at one moment. The decisions here mirror the SQL guards of the allocator; the SQL stays
 * the authority (it is atomic), this class makes the rules readable and testable without a database.
 */
final readonly class BucketCounters
{
    /**
     * @param int|null $limit null = unlimited
     */
    public function __construct(
        public ?int $limit,
        public int $held,
        public int $confirmed,
    ) {
        if ($held < 0 || $confirmed < 0 || (null !== $limit && $limit < 0)) {
            throw new \InvalidArgumentException('Capacity counters and limit cannot be negative.');
        }
    }

    public function occupied(): int
    {
        return $this->held + $this->confirmed;
    }

    /**
     * Seats a new claim could still take; null = unlimited. Never negative, also when the bucket is overbooked.
     */
    public function free(): ?int
    {
        return null === $this->limit ? null : max(0, $this->limit - $this->occupied());
    }

    /**
     * The same predicate as `"limit" IS NULL OR held + confirmed + :n <= "limit"`.
     */
    public function accepts(int $seats): bool
    {
        return null === $this->limit || $this->occupied() + $seats <= $this->limit;
    }

    public function isOverbooked(): bool
    {
        return null !== $this->limit && $this->occupied() > $this->limit;
    }

    /**
     * Would this limit leave the bucket overbooked (CAP-06: allowed, but flagged and never auto-cancelled)?
     */
    public function isBelowOccupancyAfter(?int $newLimit): bool
    {
        return null !== $newLimit && $this->occupied() > $newLimit;
    }

    /**
     * Seats that a new limit makes available to new claims (what a waitlist could be offered). An unlimited
     * bucket frees "at least one" seat when it was limited before; the exact number is unbounded.
     */
    public function seatsFreedByLimit(?int $newLimit): int
    {
        $before = $this->free();
        if (null === $before) {
            return 0;
        }

        return null === $newLimit ? 1 : max(0, max(0, $newLimit - $this->occupied()) - $before);
    }
}
