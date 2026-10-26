<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the list was closed because the event was cancelled.
 */
final readonly class CheckInListCancelled
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInListId $listId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
