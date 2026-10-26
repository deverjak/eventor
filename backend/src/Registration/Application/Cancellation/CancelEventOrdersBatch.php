<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Cancellation;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * One step of the cancellation of an event (EVT-12, chapter 04 path 6): cancels the participations of up to `limit`
 * orders that still have an active one. Every batch is its own transaction and a repeated step finds nothing to do.
 */
final readonly class CancelEventOrdersBatch
{
    public const int DEFAULT_LIMIT = 100;

    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public string $reason,
        public int $limit = self::DEFAULT_LIMIT,
    ) {}
}
