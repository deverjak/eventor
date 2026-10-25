<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\EventId;

/**
 * Public availability of an event (PUB-03).
 */
final readonly class EventAvailability
{
    /**
     * @param list<BucketAvailability> $buckets
     */
    public function __construct(
        public EventId $eventId,
        public array $buckets,
    ) {}
}
